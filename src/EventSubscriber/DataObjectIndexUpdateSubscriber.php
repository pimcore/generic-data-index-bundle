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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\Service;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final class DataObjectIndexUpdateSubscriber implements EventSubscriberInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly Installer $installer,
        private readonly IndexQueueServiceInterface $indexQueueService,
        private readonly QueueMessagesDispatcher $queueMessagesDispatcher,
        private readonly SynchronousProcessingServiceInterface $synchronousProcessing
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DataObjectEvents::POST_UPDATE => 'updateDataObject',
            DataObjectEvents::POST_ADD => 'updateDataObject',
            DataObjectEvents::POST_DELETE => 'deleteDataObject',
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

        $dataObject = $event->getObject();
        Service::useInheritedValues(true, fn () =>
            $this->indexQueueService
                ->updateIndexQueue(
                    $dataObject,
                    IndexQueueOperation::UPDATE->value,
                    $this->synchronousProcessing->isEnabled(),
                    $dataObject->hasChildren(includingUnpublished: true),
                    true
                )
                ->commit()
        );

        $this->queueMessagesDispatcher->dispatchQueueMessages();
    }

    public function deleteDataObject(DataObjectEvent $event): void
    {
        if (!$this->installer->isInstalled()) {
            return;
        }

        $dataObject = $event->getObject();
        $this->indexQueueService
            ->updateIndexQueue(
                $dataObject,
                IndexQueueOperation::DELETE->value,
                $this->synchronousProcessing->isEnabled(),
                $dataObject->hasChildren(includingUnpublished: true)
            )
            ->commit();
        $this->queueMessagesDispatcher->dispatchQueueMessages();
    }
}
