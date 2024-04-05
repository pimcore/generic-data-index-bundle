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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Listing;

/**
 * @internal
 */
final readonly class ReindexService implements ReindexServiceInterface
{
    public function __construct(
        private AssetIndexHandler $assetIndexHandler,
        private DocumentIndexHandler $documentIndexHandler,
        private DataObjectIndexHandler $dataObjectIndexHandler,
        private EnqueueServiceInterface $enqueueService,
        private SettingsStoreServiceInterface $settingsStoreService,
    ) {

    }

    /**
     * @throws Exception
     */
    public function reindexAll(): ReindexService
    {
        $this
            ->reindexClassDefinitions()
            ->reindexAssets()
            ->reindexDocuments();

        return $this;
    }

    /**
     * @throws Exception
     */
    public function reindexClassDefinitions(): ReindexService
    {
        foreach ((new Listing())->load() as $classDefinition) {
            $this->reindexClassDefinition($classDefinition);
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function reindexClassDefinition(ClassDefinition $classDefinition): ReindexService
    {
        $mappingProperties = $this->dataObjectIndexHandler->getMappingProperties($classDefinition);

        $this
            ->dataObjectIndexHandler
            ->reindexMapping(
                context: $classDefinition,
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

        return $this;
    }

    /**
     * @throws Exception
     */
    public function reindexAssets(): ReindexService
    {
        $this->assetIndexHandler->reindexMapping();

        //add assets to update queue
        $this
            ->enqueueService
            ->enqueueAssets();

        return $this;
    }

    /**
     * @throws Exception
     */
    public function reindexDocuments(): ReindexService
    {
        $this->documentIndexHandler->reindexMapping();

        //add documents to update queue
        $this
            ->enqueueService
            ->enqueueDocuments();

        return $this;
    }
}
