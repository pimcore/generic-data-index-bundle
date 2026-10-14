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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment\IndexStateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AssetIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DocumentIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
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
        private readonly IndexStateServiceInterface $indexStateService,
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

        $this->settingsStoreService->storeClassMapping(
            classDefinitionId: $classDefinition->getId(),
            data: $this->dataObjectIndexHandler->getClassMappingCheckSum($mappingProperties)
        );

        //add dataObjects to update queue
        $this
            ->enqueueService
            ->enqueueByClassDefinition($classDefinition);

        $this->refreshIndexState(IndexStateTarget::forClassDefinition($classDefinition));

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

        //add dataObjects to update queue
        $this
            ->enqueueService
            ->enqueueDataObjectFolders();

        $this->refreshIndexState(IndexStateTarget::forScope(DeploymentReindexScope::FOLDERS));

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

        //add assets to update queue
        $this
            ->enqueueService
            ->enqueueAssets();

        $this->refreshIndexState(IndexStateTarget::forScope(DeploymentReindexScope::ASSETS));

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

        //add assets to update queue
        $this
            ->enqueueService
            ->enqueueDocuments();

        $this->refreshIndexState(IndexStateTarget::forScope(DeploymentReindexScope::DOCUMENTS));

        return $this;
    }

    public function setReCreateIndex(bool $reCreateIndex): IndexUpdateService
    {
        $this->reCreateIndex = $reCreateIndex;

        return $this;
    }

    /**
     * Index settings are only applied when the index is (re)created.
     *
     * @throws Exception
     */
    private function refreshIndexState(IndexStateTarget $target): void
    {
        $this->indexStateService->refresh($target, true, $this->reCreateIndex, true);
    }
}
