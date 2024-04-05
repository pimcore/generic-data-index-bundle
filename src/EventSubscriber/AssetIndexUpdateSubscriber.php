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
use Pimcore\Event\AssetEvents;
use Pimcore\Event\Model\AssetEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final readonly class AssetIndexUpdateSubscriber implements EventSubscriberInterface
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
            AssetEvents::POST_UPDATE => 'updateAsset',
            AssetEvents::POST_ADD => 'updateAsset',
            AssetEvents::POST_DELETE => 'deleteAsset',
        ];
    }

    public function updateAsset(AssetEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

        $this->indexQueueService
            ->updateIndexQueue(
                element: $event->getAsset(),
                operation: IndexQueueOperation::UPDATE->value,
                processSynchronously: $this->synchronousProcessing->isEnabled()
            )
            ->commit();

        $this->queueMessagesDispatcher->dispatchQueueMessages();
    }

    public function deleteAsset(AssetEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

        $this->indexQueueService
            ->updateIndexQueue(
                element: $event->getAsset(),
                operation: IndexQueueOperation::DELETE->value,
                processSynchronously: $this->synchronousProcessing->isEnabled()
            )
            ->commit();

    }
}
