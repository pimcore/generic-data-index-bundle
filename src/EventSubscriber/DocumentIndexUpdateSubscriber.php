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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Installer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
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
        private Installer $installer,
        private QueueMessagesDispatcher $queueMessagesDispatcher,
        private SynchronousProcessingServiceInterface $synchronousProcessing
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

        $this->indexQueueService
            ->updateIndexQueue(
                element: $event->getDocument(),
                operation: IndexQueueOperation::UPDATE->value,
                processSynchronously: $this->synchronousProcessing->isEnabled()
            )
            ->commit();

        $this->queueMessagesDispatcher->dispatchQueueMessages();
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

    }
}
