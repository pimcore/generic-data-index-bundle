<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\AssetIndexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\DataObjectIndexService;
use Pimcore\Bundle\PortalEngineBundle\Enum\Index\Statistics\ElasticSearchAlias;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Listing;

class IndexUpdateService
{
    protected bool $reCreateIndex = false;

    public function __construct(
        protected AssetIndexService $assetIndexService,
        protected DataObjectIndexService $dataObjectIndexService,
        protected IndexQueueService $indexQueueService,
    ) {

    }

    /**
     * @return $this
     */
    public function updateAll()
    {

        $this
            ->updateClassDefinitions()
            ->updateAssets();

        return $this;
    }

    /**
     * @return $this
     */
    public function updateClassDefinitions()
    {
        foreach ((new Listing())->load() as $classDefinition) {
            $this->updateClassDefinition($classDefinition);
        }

        return $this;
    }

    /**
     * @param ClassDefinition $classDefinition
     *
     * @return $this
     */
    public function updateClassDefinition($classDefinition)
    {
        if ($this->reCreateIndex) {
            $this
                ->dataObjectIndexService
                ->deleteIndex($classDefinition);
        }

        $this
            ->dataObjectIndexService
            ->updateMapping($classDefinition, $this->reCreateIndex);

        //add dataObjects to update queue
        $this
            ->indexQueueService
            ->updateDataObjects($classDefinition);

        //@todo $this
        //    ->dataObjectIndexService
        //    ->addClassDefinitionToAlias($classDefinition, ElasticSearchAlias::CLASS_DEFINITIONS);

        return $this;
    }

    public function updateAssets(): IndexUpdateService
    {

        if ($this->reCreateIndex) {
            $this
                ->assetIndexService
                ->deleteIndex();
        }

        $this
            ->assetIndexService
            ->updateMapping($this->reCreateIndex);

        //add assets to update queue
        $this
            ->indexQueueService
            ->updateAssets();

        return $this;
    }

    /**
     * @param bool $reCreateIndex
     *
     * @return IndexUpdateService
     */
    public function setReCreateIndex(bool $reCreateIndex): self
    {
        $this->reCreateIndex = $reCreateIndex;

        return $this;
    }
}
