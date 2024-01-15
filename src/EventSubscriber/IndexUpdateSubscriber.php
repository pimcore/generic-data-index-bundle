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

namespace Pimcore\Bundle\GenericDataIndexBundle\EventSubscriber;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\AssetIndexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\DataObjectIndexService;
use Pimcore\Bundle\PortalEngineBundle\Enum\Index\Statistics\ElasticSearchAlias;
use Pimcore\Event\AssetEvents;
use Pimcore\Event\DataObjectClassDefinitionEvents;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\Model\AssetEvent;
use Pimcore\Event\Model\DataObject\ClassDefinitionEvent;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Event\Model\TagEvent;
use Pimcore\Event\TagEvents;
use Pimcore\Logger;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Element\Service;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IndexUpdateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected readonly IndexQueueService $indexQueueService,
        protected readonly DataObjectIndexService $dataObjectIndexService,
        protected readonly AssetIndexService $assetIndexService,
        // protected readonly Installer $installer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DataObjectEvents::POST_UPDATE => 'updateDataObject',
            DataObjectEvents::POST_ADD => 'updateDataObject',
            DataObjectEvents::PRE_DELETE => 'deleteDataObject',
            DataObjectClassDefinitionEvents::POST_UPDATE => 'updateDataObjectMapping',
            DataObjectClassDefinitionEvents::POST_ADD => 'addDataObjectMapping',
            DataObjectClassDefinitionEvents::POST_DELETE => 'deleteDataObjectIndex',
            AssetEvents::POST_UPDATE => 'updateAsset',
            AssetEvents::POST_ADD => 'updateAsset',
            AssetEvents::POST_DELETE => 'deleteAsset',
            TagEvents::PRE_DELETE => 'deleteTag',
            TagEvents::POST_ADD_TO_ELEMENT => 'updateTagAssignment',
            TagEvents::POST_REMOVE_FROM_ELEMENT => 'updateTagAssignment',
        ];
    }

    public function updateDataObject(DataObjectEvent $event): void
    {
        /* if (!$this->installer->isInstalled()) {
             return;
        }*/

        //do not update index when auto save or only saving version
        if (
            ($event->hasArgument('isAutoSave') && $event->getArgument('isAutoSave')) ||
            ($event->hasArgument('saveVersionOnly') && $event->getArgument('saveVersionOnly'))
        ) {
            return;
        }

        $inheritanceBackup = AbstractObject::getGetInheritedValues();
        AbstractObject::setGetInheritedValues(true);

        $this->indexQueueService
            ->updateIndexQueue(
                element: $event->getObject(),
                operation: IndexQueueOperation::UPDATE->value,
                doIndexElement: true
            )
            ->commit();

        AbstractObject::setGetInheritedValues($inheritanceBackup);
    }

    public function deleteDataObject(DataObjectEvent $event): void
    {
        /*if (!$this->installer->isInstalled()) {
            return;
        }*/

        $this->indexQueueService
            ->updateIndexQueue(
                element: $event->getObject(),
                operation: IndexQueueOperation::DELETE->value,
                doIndexElement: true
            )
            ->commit();
    }

    /**
     * @throws Exception
     */
    public function addDataObjectMapping(ClassDefinitionEvent $event): void
    {
        /*if (!$this->installer->isInstalled()) {
            return;
        }*/

        $classDefinition = $event->getClassDefinition();
        $this->dataObjectIndexService
            ->updateMapping($classDefinition, true)
            ->addClassDefinitionToAlias($classDefinition, ElasticSearchAlias::CLASS_DEFINITIONS);
    }

    /**
     * @throws Exception
     */
    public function updateDataObjectMapping(ClassDefinitionEvent $event): void
    {
        /*if (!$this->installer->isInstalled()) {
            return;
        }*/

        $classDefinition = $event->getClassDefinition();
        $this->dataObjectIndexService
            ->updateMapping($classDefinition)
            ->addClassDefinitionToAlias($classDefinition, ElasticSearchAlias::CLASS_DEFINITIONS);
        $this->indexQueueService->updateDataObjects($classDefinition);
    }

    public function deleteDataObjectIndex(ClassDefinitionEvent $event): void
    {
        /*if (!$this->installer->isInstalled()) {
            return;
        }*/

        $classDefinition = $event->getClassDefinition();

        try {
            $this->dataObjectIndexService
                ->deleteIndex($classDefinition)
                ->removeClassDefinitionFromAlias($classDefinition, ElasticSearchAlias::CLASS_DEFINITIONS)
            ;
        } catch (Exception $e) {
            Logger::err($e);
        }
    }

    /**
     * @param AssetEvent $event
     */
    public function updateAsset(AssetEvent $event): void
    {
        /*if (!$this->installer->isInstalled()) {
            return;
        }*/

        $this->indexQueueService->updateIndexQueue(
            element: $event->getAsset(),
            operation: IndexQueueOperation::UPDATE->value,
            doIndexElement: true);
    }

    public function deleteAsset(AssetEvent $event): void
    {
        /*if (!$this->installer->isInstalled()) {
            return;
        }*/

        $this->indexQueueService
            ->updateIndexQueue(
                element: $event->getAsset(),
                operation: IndexQueueOperation::DELETE->value,
                doIndexElement: true
            )
            ->commit();

    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function deleteTag(TagEvent $event): void
    {
        /*if (!$this->installer->isInstalled()) {
            return;
        }*/

        $this->indexQueueService->updateByTag($event->getTag());
    }

    public function updateTagAssignment(TagEvent $event): void
    {
        /*if (!$this->installer->isInstalled()) {
            return;
        }*/

        $element = Service::getElementById($event->getArgument('elementType'), $event->getArgument('elementId'));

        //only update when element is object or asset
        if ($element instanceof AbstractObject || $element instanceof Asset) {
            $this->indexQueueService
                ->updateIndexQueue(
                    element: $element,
                    operation: IndexQueueOperation::UPDATE->value,
                    doIndexElement: true
                )
                ->commit();
        }
    }
}
