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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AssetIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DocumentIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\IndexHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;

/**
 * Owns the per-element-type index handlers and provisions (deletes + recreates) a local index
 * for a snapshot import target, independent of the import decision logic (verify before
 * provision, stamp only on complete replay), which stays in {@see SnapshotImporter}.
 *
 * @internal
 */
final class IndexProvisioner implements IndexProvisionerInterface
{
    public function __construct(
        private readonly SearchIndexServiceInterface $searchIndexService,
        private readonly DataObjectIndexHandler $dataObjectIndexHandler,
        private readonly AssetIndexHandler $assetIndexHandler,
        private readonly DocumentIndexHandler $documentIndexHandler,
        private readonly SettingsStoreServiceInterface $settingsStoreService,
    ) {
    }

    public function provision(IndexTarget $target): void
    {
        $handler = $this->handlerFor($target);
        $context = $target->classDefinition;
        if ($this->searchIndexService->existsAlias($target->aliasName)) {
            $handler->deleteIndex($context);
        }
        $mappingProperties = $handler->getMappingProperties($context);
        $handler->updateMapping(context: $context, forceCreateIndex: true, mappingProperties: $mappingProperties);
    }

    public function computeClassMappingChecksum(IndexTarget $target): ?int
    {
        if (!$target->isClassIndex()) {
            return null;
        }
        $handler = $this->handlerFor($target);

        return $handler->getClassMappingCheckSum($handler->getMappingProperties($target->classDefinition));
    }

    public function stampClassMapping(IndexTarget $target, int $checksum): void
    {
        $this->settingsStoreService->storeClassMapping((string) $target->getClassId(), $checksum);
    }

    private function handlerFor(IndexTarget $target): IndexHandlerInterface
    {
        return match (true) {
            $target->identity->isAsset() => $this->assetIndexHandler,
            $target->identity->isDocument() => $this->documentIndexHandler,
            $target->identity->isDataObject() => $this->dataObjectIndexHandler,
            default => throw new SnapshotImportException(sprintf('Unknown element type "%s"', $target->elementType)),
        };
    }
}
