<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment;

use JsonException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexState;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DataObjectTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DocumentTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AssetIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DocumentIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\IndexHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;

/**
 * @internal
 */
final readonly class IndexStateService implements IndexStateServiceInterface
{
    /**
     * Raise when a bundle change alters the indexed values without changing the mapping, so
     * that deployment:reindex rebuilds the affected indices from the database.
     */
    public const BUNDLE_DATA_VERSION = 1;

    /**
     * @param array{assets: int, documents: int, folders: int, classes: array<string, int>} $indexVersions
     */
    public function __construct(
        private SettingsStoreServiceInterface $settingsStoreService,
        private SearchIndexServiceInterface $searchIndexService,
        private SearchIndexConfigServiceInterface $searchIndexConfigService,
        private AssetIndexHandler $assetIndexHandler,
        private DocumentIndexHandler $documentIndexHandler,
        private DataObjectIndexHandler $dataObjectIndexHandler,
        private AssetTypeAdapter $assetTypeAdapter,
        private DocumentTypeAdapter $documentTypeAdapter,
        private DataObjectTypeAdapter $dataObjectTypeAdapter,
        private array $indexVersions = ['assets' => 0, 'documents' => 0, 'folders' => 0, 'classes' => []],
    ) {
    }

    public function getCurrentState(IndexStateTarget $target): IndexState
    {
        $handler = $this->getHandler($target);
        $context = $target->getClassDefinition();

        return new IndexState(
            (string) $handler->getClassMappingCheckSum($handler->getMappingProperties($context)),
            // Same order-independent checksum as the mapping, applied to the merged index_settings.
            (string) $handler->getClassMappingCheckSum($this->searchIndexConfigService->getIndexSettings()),
            sprintf('%d.%d', self::BUNDLE_DATA_VERSION, $this->getProjectDataVersion($target)),
        );
    }

    public function getStoredState(IndexStateTarget $target): IndexState
    {
        $classDefinition = $target->getClassDefinition();
        if ($classDefinition !== null) {
            $classMappingCheckSum = $this->settingsStoreService->getClassMappingCheckSum($classDefinition->getId());
            $mappingChecksum = $classMappingCheckSum === null ? null : (string) $classMappingCheckSum;
        } else {
            $mappingChecksum = $this->settingsStoreService->getIndexState($this->key($target, 'mapping'));
        }

        return new IndexState(
            $mappingChecksum,
            $this->settingsStoreService->getIndexState($this->key($target, 'settings')),
            $this->settingsStoreService->getIndexState($this->key($target, 'data_version')),
        );
    }

    public function indexExists(IndexStateTarget $target): bool
    {
        $aliasName = match ($target->getScope()) {
            DeploymentReindexScope::ASSETS => $this->assetTypeAdapter->getAliasIndexName(),
            DeploymentReindexScope::DOCUMENTS => $this->documentTypeAdapter->getAliasIndexName(),
            DeploymentReindexScope::CLASSES,
            DeploymentReindexScope::FOLDERS => $this->dataObjectTypeAdapter->getAliasIndexName(
                $target->getClassDefinition()
            ),
        };

        return $this->searchIndexService->existsAlias($aliasName);
    }

    public function store(IndexStateTarget $target, IndexState $state): void
    {
        $mappingChecksum = $state->getMappingChecksum();
        if ($mappingChecksum !== null && !$target->isClassIndex()) {
            $this->settingsStoreService->storeIndexState($this->key($target, 'mapping'), $mappingChecksum);
        }

        if ($state->getSettingsHash() !== null) {
            $this->settingsStoreService->storeIndexState($this->key($target, 'settings'), $state->getSettingsHash());
        }

        if ($state->getDataVersion() !== null) {
            $this->settingsStoreService->storeIndexState(
                $this->key($target, 'data_version'),
                $state->getDataVersion()
            );
        }
    }

    public function refresh(
        IndexStateTarget $target,
        bool $mapping,
        bool $settings,
        bool $dataVersion
    ): void {
        $stored = $this->getStoredState($target);
        // Class checksums are kept up to date by the class definition reindex itself.
        $mapping = $mapping && !$target->isClassIndex() && $stored->getMappingChecksum() !== null;
        $settings = $settings && $stored->getSettingsHash() !== null;
        $dataVersion = $dataVersion && $stored->getDataVersion() !== null;
        if (!$mapping && !$settings && !$dataVersion) {
            return;
        }

        $current = $this->getCurrentState($target);
        $state = new IndexState(
            $mapping ? $current->getMappingChecksum() : null,
            $settings ? $current->getSettingsHash() : null,
            $dataVersion ? $current->getDataVersion() : null,
        );
        $this->store($target, $state);
    }

    public function invalidateMapping(IndexStateTarget $target): void
    {
        if ($target->isClassIndex()) {
            // Class indices without a stored checksum are reindexed, see ClassDefinitionReindexService.
            $this->settingsStoreService->removeClassMapping((string) $target->getClassDefinition()?->getId());

            return;
        }

        $this->settingsStoreService->storeIndexState($this->key($target, 'mapping'), self::INVALIDATED);
    }

    public function isMappingCurrent(IndexStateTarget $target): ?bool
    {
        $stored = $this->getStoredState($target)->getMappingChecksum();
        if ($stored === null) {
            return null;
        }

        $handler = $this->getHandler($target);
        $mappingProperties = $handler->getMappingProperties($target->getClassDefinition());
        if ($stored === (string) $handler->getClassMappingCheckSum($mappingProperties)) {
            return true;
        }

        return $target->isClassIndex() && $stored === (string) $this->getLegacyClassMappingCheckSum($mappingProperties);
    }

    /**
     * Checksum as the class definition reindex calculated it before the mapping properties were
     * normalized (depending on the array key order); recognises entries stored by an older bundle
     * version, see ClassDefinitionReindexService.
     *
     * @throws JsonException
     */
    private function getLegacyClassMappingCheckSum(array $mappingProperties): int
    {
        return crc32(json_encode($mappingProperties, JSON_THROW_ON_ERROR));
    }

    private function getHandler(IndexStateTarget $target): IndexHandlerInterface
    {
        return match ($target->getScope()) {
            DeploymentReindexScope::ASSETS => $this->assetIndexHandler,
            DeploymentReindexScope::DOCUMENTS => $this->documentIndexHandler,
            DeploymentReindexScope::CLASSES, DeploymentReindexScope::FOLDERS => $this->dataObjectIndexHandler,
        };
    }

    private function getProjectDataVersion(IndexStateTarget $target): int
    {
        $classDefinition = $target->getClassDefinition();
        if ($classDefinition !== null) {
            return $this->indexVersions['classes'][$classDefinition->getId()] ?? 0;
        }

        return match ($target->getScope()) {
            DeploymentReindexScope::ASSETS => $this->indexVersions['assets'],
            DeploymentReindexScope::DOCUMENTS => $this->indexVersions['documents'],
            DeploymentReindexScope::FOLDERS => $this->indexVersions['folders'],
            DeploymentReindexScope::CLASSES => 0,
        };
    }

    private function key(IndexStateTarget $target, string $value): string
    {
        return $target->getKey() . '_' . $value;
    }
}
