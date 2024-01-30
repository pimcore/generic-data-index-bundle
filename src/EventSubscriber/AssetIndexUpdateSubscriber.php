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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueService;
use Pimcore\Event\AssetEvents;
use Pimcore\Event\Model\AssetEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final class AssetIndexUpdateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly IndexQueueService $indexQueueService,
        private readonly Installer $installer,
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
                doIndexElement: true
            )
            ->commit()
            ->dispatchQueueMessages();
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
                doIndexElement: true
            )
            ->commit();

    }
}
