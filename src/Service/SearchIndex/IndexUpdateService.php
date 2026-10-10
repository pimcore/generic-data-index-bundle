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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AssetIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DocumentIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workflow\WorkflowPlaceServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Listing;

/**
 * @internal
 */
final class IndexUpdateService implements IndexUpdateServiceInterface
{
    private bool $reCreateIndex = false;

    public function __construct(
        private readonly AssetIndexHandler $assetIndexHandler,
        private readonly DocumentIndexHandler $documentIndexHandler,
        private readonly DataObjectIndexHandler $dataObjectIndexHandler,
        private readonly EnqueueServiceInterface $enqueueService,
        private readonly SettingsStoreServiceInterface $settingsStoreService,
        private readonly WorkflowPlaceServiceInterface $workflowPlaceService,
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
    ) {

    }

    /**
     * @throws Exception
     */
    public function updateAll(): IndexUpdateService
    {
        $this
            ->updateDataObjectFolders()
            ->updateClassDefinitions()
            ->updateAssets()
            ->updateDocuments();

        return $this;
    }

    /**
     * @throws Exception
     */
    public function updateClassDefinitions(): IndexUpdateService
    {
        foreach ((new Listing())->load() as $classDefinition) {
            $this->updateClassDefinition($classDefinition);
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function updateClassDefinition(ClassDefinition $classDefinition): IndexUpdateService
    {
        if ($this->reCreateIndex) {
            $this->dataObjectIndexHandler
                ->deleteIndex($classDefinition);
        }

        $mappingProperties = $this->dataObjectIndexHandler->getMappingProperties($classDefinition);

        $this
            ->dataObjectIndexHandler
            ->updateMapping(
                context: $classDefinition,
                forceCreateIndex: $this->reCreateIndex,
                mappingProperties: $mappingProperties
            );

        $this->storeWorkflowPlacesMapping(
            $this->searchIndexConfigService->getIndexName($classDefinition->getName(), true)
        );
        $this->settingsStoreService->storeClassMapping(
            classDefinitionId: $classDefinition->getId(),
            data: $this->dataObjectIndexHandler->getClassMappingCheckSum($mappingProperties)
        );

        //add dataObjects to update queue
        $this
            ->enqueueService
            ->enqueueByClassDefinition($classDefinition);

        return $this;
    }

    /**
     * @throws Exception
     */
    public function updateDataObjectFolders(): IndexUpdateService
    {
        if ($this->reCreateIndex) {
            $this->dataObjectIndexHandler
                ->deleteIndex();
        }

        $this
            ->dataObjectIndexHandler
            ->updateMapping(
                forceCreateIndex: $this->reCreateIndex
            );

        $this->storeWorkflowPlacesMapping(
            $this->searchIndexConfigService->getIndexName(IndexName::DATA_OBJECT_FOLDER->value)
        );

        //add dataObjects to update queue
        $this
            ->enqueueService
            ->enqueueDataObjectFolders();

        return $this;
    }

    /**
     * @throws Exception
     */
    public function updateAssets(): IndexUpdateService
    {

        if ($this->reCreateIndex) {
            $this->assetIndexHandler
                ->deleteIndex();
        }

        $this
            ->assetIndexHandler
            ->updateMapping(
                forceCreateIndex: $this->reCreateIndex
            );

        $this->storeWorkflowPlacesMapping(
            $this->searchIndexConfigService->getIndexName(IndexName::ASSET->value)
        );

        //add assets to update queue
        $this
            ->enqueueService
            ->enqueueAssets();

        return $this;
    }

    /**
     * @throws Exception
     */
    public function updateDocuments(): IndexUpdateService
    {

        if ($this->reCreateIndex) {
            $this->documentIndexHandler
                ->deleteIndex();
        }

        $this
            ->documentIndexHandler
            ->updateMapping(
                forceCreateIndex: $this->reCreateIndex
            );

        $this->storeWorkflowPlacesMapping(
            $this->searchIndexConfigService->getIndexName(IndexName::DOCUMENT->value)
        );

        //add assets to update queue
        $this
            ->enqueueService
            ->enqueueDocuments();

        return $this;
    }

    public function setReCreateIndex(bool $reCreateIndex): IndexUpdateService
    {
        $this->reCreateIndex = $reCreateIndex;

        return $this;
    }

    /**
     * Documents with places are written again once the workflowPlaces mapping applied to the index changed:
     * the places are part of the document source even if the mapping did not know the workflow yet.
     *
     * @throws Exception
     */
    private function storeWorkflowPlacesMapping(string $indexName): void
    {
        $this->settingsStoreService->storeWorkflowPlacesMappingChecksum(
            $indexName,
            crc32(json_encode($this->workflowPlaceService->getMapping(), JSON_THROW_ON_ERROR))
        );
    }
}
