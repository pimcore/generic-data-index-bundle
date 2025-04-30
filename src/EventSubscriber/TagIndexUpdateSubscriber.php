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

namespace Pimcore\Bundle\GenericDataIndexBundle\EventSubscriber;

use Doctrine\DBAL\Exception as DBALException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Installer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use Pimcore\Event\Model\TagEvent;
use Pimcore\Event\TagEvents;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final readonly class TagIndexUpdateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Installer $installer,
        private EnqueueServiceInterface $enqueueService,
        private IndexQueueServiceInterface $indexQueueService,
        private QueueMessagesDispatcher $queueMessagesDispatcher,
        private SynchronousProcessingServiceInterface $synchronousProcessing
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TagEvents::PRE_DELETE => 'deleteTag',
            TagEvents::POST_ADD_TO_ELEMENT => 'updateTagAssignment',
            TagEvents::POST_REMOVE_FROM_ELEMENT => 'updateTagAssignment',
            TagEvents::POST_BATCH_ASSIGN_TAGS_TO_ELEMENT => 'batchUpdateTagAssignment',
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

        $element = Service::getElementById(
            $event->getArgument('elementType'),
            $event->getArgument('elementId')
        );
        if (!$element instanceof ElementInterface) {
            return;
        }

        $this->indexQueueService
            ->updateIndexQueue(
                element: $element,
                operation: IndexQueueOperation::UPDATE->value,
                processSynchronously: $this->synchronousProcessing->isEnabled()
            )
            ->commit();

        $this->queueMessagesDispatcher->dispatchQueueMessages();
    }

    public function batchUpdateTagAssignment(TagEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

        $type = $event->getArgument('elementType');
        $ids = $event->getArgument('elementIds');

        foreach ($ids as $id) {
            $element = Service::getElementById($type, $id);
            if (!$element instanceof ElementInterface) {
                continue;
            }

            $this->indexQueueService
                ->updateIndexQueue(
                    element: $element,
                    operation: IndexQueueOperation::UPDATE->value,
                    processSynchronously: $this->synchronousProcessing->isEnabled()
                );
        }

        $this->indexQueueService->commit();
        $this->queueMessagesDispatcher->dispatchQueueMessages();

    }
}
