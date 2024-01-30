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

namespace Pimcore\Bundle\GenericDataIndexBundle\EventSubscriber;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Installer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Event\DataObjectClassDefinitionEvents;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\Model\DataObject\ClassDefinitionEvent;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\AbstractObject;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final class DataObjectIndexUpdateSubscriber implements EventSubscriberInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly Installer $installer,
        private readonly IndexQueueService $indexQueueService,
        private readonly DataObjectIndexHandler $dataObjectMappingHandler,
        private readonly EnqueueService $enqueueService,
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
        ];
    }

    public function updateDataObject(DataObjectEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

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
            ->commit()
            ->dispatchQueueMessages();

        AbstractObject::setGetInheritedValues($inheritanceBackup);
    }

    public function deleteDataObject(DataObjectEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

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
        if (!$this->installer->isInstalled()) {
            return;
        }

        $classDefinition = $event->getClassDefinition();

        $this->dataObjectMappingHandler
            ->updateMapping(
                context: $classDefinition,
                forceCreateIndex: true
            );
    }

    /**
     * @throws Exception
     */
    public function updateDataObjectMapping(ClassDefinitionEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

        $classDefinition = $event->getClassDefinition();

        $this->dataObjectMappingHandler
            ->updateMapping(
                context: $classDefinition,
                forceCreateIndex: true
            );

        $this->enqueueService
            ->enqueueByClassDefinition($classDefinition)
            ->dispatchQueueMessages();
    }

    public function deleteDataObjectIndex(ClassDefinitionEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

        $classDefinition = $event->getClassDefinition();

        try {
            $this->dataObjectMappingHandler
                ->deleteIndex($classDefinition);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
