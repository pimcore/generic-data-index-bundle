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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Installer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexElementIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingRelatedIdsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use Pimcore\Event\DocumentEvents;
use Pimcore\Event\Model\DocumentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final readonly class DocumentIndexUpdateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private IndexQueueServiceInterface $indexQueueService,
        private IndexElementIndexServiceInterface $indexElementIndexService,
        private Installer $installer,
        private QueueMessagesDispatcher $queueMessagesDispatcher,
        private SynchronousProcessingServiceInterface $synchronousProcessing,
        private SynchronousProcessingRelatedIdsServiceInterface $synchronousProcessingRelatedIds
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DocumentEvents::POST_UPDATE=> 'updateDocument',
            DocumentEvents::POST_ADD => 'updateDocument',
            DocumentEvents::POST_DELETE => 'deleteDocument',
        ];
    }

    public function updateDocument(DocumentEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }
        $document = $event->getDocument();
        $this->indexQueueService
            ->updateIndexQueue(
                element: $event->getDocument(),
                operation: IndexQueueOperation::UPDATE->value,
                processSynchronously: $this->synchronousProcessing->isEnabled(),
                enqueueRelatedItemsAsync: $this->synchronousProcessingRelatedIds->isEnabled() === false
            )
            ->commit();

        $this->queueMessagesDispatcher->dispatchQueueMessages();
        $this->indexElementIndexService->updateSiblings($document);
    }

    public function deleteDocument(DocumentEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

        $this->indexQueueService
            ->updateIndexQueue(
                element: $event->getDocument(),
                operation: IndexQueueOperation::DELETE->value,
                processSynchronously: $this->synchronousProcessing->isEnabled()
            )
            ->commit();
        $this->queueMessagesDispatcher->dispatchQueueMessages();
    }
}
