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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Messenger\TransportName;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateSiblingsMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Document\SearchHelper;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcherInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingRelatedIdsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Event\DocumentEvents;
use Pimcore\Event\Model\DocumentEvent;
use Pimcore\Extension\Bundle\Installer\InstallerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * @internal
 */
final readonly class DocumentIndexUpdateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private IndexQueueServiceInterface $indexQueueService,
        private InstallerInterface $installer,
        private QueueMessagesDispatcherInterface $queueMessagesDispatcher,
        private RuntimeCacheResolverInterface $runtimeCacheResolver,
        private SynchronousProcessingServiceInterface $synchronousProcessing,
        private SynchronousProcessingRelatedIdsServiceInterface $synchronousProcessingRelatedIds,
        private MessageBusInterface $messageBus
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DocumentEvents::POST_ADD => 'addDocument',
            DocumentEvents::POST_UPDATE=> 'updateDocument',
            DocumentEvents::POST_DELETE => 'deleteDocument',
        ];
    }

    public function addDocument(DocumentEvent $event): void
    {
        $this->updateData($event);
    }

    public function updateDocument(DocumentEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

        $this->messageBus->dispatch(
            new UpdateSiblingsMessage(
                $event->getDocument()->getId(),
                ElementType::DOCUMENT,
                false
            ),
            // In synchronous mode (e.g. CLI, tests) route to the sync transport so
            // siblings are reindexed immediately; otherwise process on the async queue.
            $this->synchronousProcessing->isEnabled()
                ? [new TransportNamesStamp(TransportName::SYNC->value)]
                : []
        );

        $this->updateData($event);
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

    private function updateData(DocumentEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }
        $this->indexQueueService
            ->updateIndexQueue(
                element: $event->getDocument(),
                operation: IndexQueueOperation::UPDATE->value,
                processSynchronously: $this->synchronousProcessing->isEnabled(),
                enqueueRelatedItemsAsync: $this->synchronousProcessingRelatedIds->isEnabled() === false
            )
            ->commit();

        $this->queueMessagesDispatcher->dispatchQueueMessages();

        //clear runtime cache for this object
        $cacheKey = SearchHelper::DOCUMENT_SEARCH . '_' . $event->getDocument()->getId();
        if ($this->runtimeCacheResolver->isRegistered($cacheKey)) {
            $this->runtimeCacheResolver->set($cacheKey, null);
        }
    }
}
