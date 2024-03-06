<?php
declare(strict_types=1);

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

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AssetIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DocumentIndexHandler;
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
    ) {

    }

    /**
     * @throws Exception
     */
    public function updateAll(): IndexUpdateService
    {
        $this
            //->updateClassDefinitions()
            //->updateAssets()
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

        $this
            ->dataObjectIndexHandler
            ->updateMapping(
                context: $classDefinition,
                forceCreateIndex: $this->reCreateIndex
            );

        //add dataObjects to update queue
        $this
            ->enqueueService
            ->enqueueByClassDefinition($classDefinition);

        //@todo $this
        //    ->dataObjectIndexService
        //    ->addClassDefinitionToAlias($classDefinition, ElasticSearchAlias::CLASS_DEFINITIONS);

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

        return $this;
    }

    public function setReCreateIndex(bool $reCreateIndex): IndexUpdateService
    {
        $this->reCreateIndex = $reCreateIndex;

        return $this;
    }
}
