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

use Doctrine\DBAL\Exception as DBALException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Installer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use Pimcore\Event\Model\TagEvent;
use Pimcore\Event\TagEvents;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\Element\Service;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final class TagIndexUpdateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Installer $installer,
        private readonly EnqueueServiceInterface $enqueueService,
        private readonly IndexQueueServiceInterface $indexQueueService,
        private readonly QueueMessagesDispatcher $queueMessagesDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TagEvents::PRE_DELETE => 'deleteTag',
            TagEvents::POST_ADD_TO_ELEMENT => 'updateTagAssignment',
            TagEvents::POST_REMOVE_FROM_ELEMENT => 'updateTagAssignment',
        ];
    }

    /**
     * @throws DBALException
     */
    public function deleteTag(TagEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

        $this->enqueueService
            ->enqueueByTag($event->getTag())
            ->dispatchQueueMessages();
    }

    public function updateTagAssignment(TagEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

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

            $this->queueMessagesDispatcher->dispatchQueueMessages();
        }
    }
}
