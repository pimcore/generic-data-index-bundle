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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexStatusState;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexStatusException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexStatus;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexStatusReport;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexCountRepositoryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DefaultSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment\IndexStateServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

/**
 * @internal
 */
final readonly class IndexStatusService implements IndexStatusServiceInterface
{
    public function __construct(
        private IndexStateTargetResolverInterface $targetResolver,
        private IndexStateServiceInterface $indexStateService,
        private ClassDefinitionReindexServiceInterface $classDefinitionReindexService,
        private SearchIndexServiceInterface $searchIndexService,
        private IndexCountRepositoryInterface $countRepository,
        private IndexRebuildStateServiceInterface $rebuildStateService,
        private ?MessageCountAwareInterface $failedTransport = null,
    ) {
    }

    public function getAll(): IndexStatusReport
    {
        $copyTargets = $this->copyTargets();
        $indices = [];
        foreach ($this->targetResolver->all() as $target) {
            $indices[] = $this->buildStatus($target, $copyTargets);
        }

        return new IndexStatusReport(
            $indices,
            $this->countRepository->countQueueEntries(),
            $this->failedTransport?->getMessageCount()
        );
    }

    public function getStatusForClassDefinition(ClassDefinition $classDefinition): IndexStatus
    {
        return $this->getStatus(IndexStateTarget::forClassDefinition($classDefinition));
    }

    public function getAssetStatus(): IndexStatus
    {
        return $this->getStatus(IndexStateTarget::forScope(DeploymentReindexScope::ASSETS));
    }

    public function getDocumentStatus(): IndexStatus
    {
        return $this->getStatus(IndexStateTarget::forScope(DeploymentReindexScope::DOCUMENTS));
    }

    public function getDataObjectFolderStatus(): IndexStatus
    {
        return $this->getStatus(IndexStateTarget::forScope(DeploymentReindexScope::FOLDERS));
    }

    public function getStatusByIndexName(string $indexName): IndexStatus
    {
        $target = $this->targetResolver->fromIndexName($indexName)
            ?? throw new IndexStatusException(sprintf('No index "%s" is known.', $indexName));

        return $this->getStatus($target);
    }

    public function waitUntilComplete(string $indexName, int $timeoutSeconds, int $pollIntervalSeconds = 5): IndexStatus
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $pollIntervalSeconds = max(1, $pollIntervalSeconds);
        while (true) {
            $status = $this->getStatusByIndexName($indexName);
            if ($status->isComplete()) {
                return $status;
            }
            if (microtime(true) >= $deadline) {
                throw new IndexStatusException(sprintf(
                    'Index "%s" is still %s after %d seconds.',
                    $status->getIndexName(),
                    $status->getState()->value,
                    $timeoutSeconds
                ));
            }
            sleep($pollIntervalSeconds);
        }
    }

    /**
     * @internal the target model is not part of the public API; use the named methods
     */
    public function getStatus(IndexStateTarget $target): IndexStatus
    {
        return $this->buildStatus($target, $this->copyTargets());
    }

    /**
     * @param string[] $copyTargets names of the indices a running reindex task writes into
     */
    private function buildStatus(IndexStateTarget $target, array $copyTargets): IndexStatus
    {
        $aliasName = $this->targetResolver->aliasName($target);
        $elementIndexName = $this->targetResolver->elementIndexName($target);
        $exists = $this->indexStateService->indexExists($target);
        $copyRunning = $this->isCopyRunning($aliasName, $copyTargets);

        $documentCount = $exists ? $this->searchIndexService->countDocuments($aliasName) : 0;
        $documentsWithoutChecksum = $exists ? $this->searchIndexService->countDocuments($aliasName, [
            'bool' => ['must_not' => [['exists' => ['field' => self::checksumPath()]]]],
        ]) : 0;
        $elementCount = $this->countRepository->countElements($target);
        $pendingEntries = $this->countRepository->countQueueEntries($elementIndexName);
        $rebuildQueuedAt = $this->rebuildStateService->getQueuedAt($target);

        return new IndexStatus(
            indexName: $aliasName,
            label: $target->getLabel(),
            state: $this->deriveState(
                $exists,
                $copyRunning,
                $documentCount,
                $documentsWithoutChecksum,
                $elementCount,
                $pendingEntries,
                $rebuildQueuedAt
            ),
            documentCount: $documentCount,
            elementCount: $elementCount,
            pendingEntries: $pendingEntries,
            documentsWithoutChecksum: $documentsWithoutChecksum,
            copyRunning: $copyRunning,
            mappingCurrent: $exists ? $this->isMappingCurrent($target) : null,
            settingsCurrent: $exists ? $this->isSettingsCurrent($target) : null,
            rebuildQueuedAt: $rebuildQueuedAt,
        );
    }

    private function deriveState(
        bool $exists,
        bool $copyRunning,
        int $documentCount,
        int $documentsWithoutChecksum,
        int $elementCount,
        int $pendingEntries,
        ?int $rebuildQueuedAt
    ): IndexStatusState {
        return match (true) {
            !$exists => IndexStatusState::MISSING,
            $copyRunning => IndexStatusState::COPYING,
            $documentsWithoutChecksum > 0 => IndexStatusState::DEGRADED,
            $rebuildQueuedAt !== null && ($pendingEntries > 0 || $documentCount < $elementCount)
                => IndexStatusState::REBUILDING,
            $documentCount < $elementCount && $pendingEntries === 0 => IndexStatusState::INCOMPLETE,
            default => IndexStatusState::COMPLETE,
        };
    }

    /**
     * Classes compare through the class definition reindex, which also accepts a checksum stored by an older
     * version; the other indices have a stored mapping state only after the extended deployment reindex ran.
     */
    private function isMappingCurrent(IndexStateTarget $target): ?bool
    {
        $classDefinition = $target->getClassDefinition();
        if ($classDefinition !== null) {
            return !$this->classDefinitionReindexService->isMappingChanged($classDefinition);
        }

        $stored = $this->indexStateService->getStoredState($target)->getMappingChecksum();
        if ($stored === null) {
            return null;
        }

        return $stored === $this->indexStateService->getCurrentState($target)->getMappingChecksum();
    }

    private function isSettingsCurrent(IndexStateTarget $target): ?bool
    {
        $stored = $this->indexStateService->getStoredState($target)->getSettingsHash();
        if ($stored === null) {
            return null;
        }

        return $stored === $this->indexStateService->getCurrentState($target)->getSettingsHash();
    }

    private static function checksumPath(): string
    {
        return FieldCategory::SYSTEM_FIELDS->value . '.' . SystemField::CHECKSUM->value;
    }

    /**
     * @return string[]
     */
    private function copyTargets(): array
    {
        return array_map(
            static fn (array $task): string => $task['target'],
            $this->searchIndexService->getReindexTasks()
        );
    }

    /**
     * @param string[] $copyTargets
     */
    private function isCopyRunning(string $aliasName, array $copyTargets): bool
    {
        $versions = [DefaultSearchService::INDEX_VERSION_ODD, DefaultSearchService::INDEX_VERSION_EVEN];
        foreach ($copyTargets as $copyTarget) {
            foreach ($versions as $version) {
                if ($copyTarget === $aliasName . '-' . $version) {
                    return true;
                }
            }
        }

        return false;
    }
}
