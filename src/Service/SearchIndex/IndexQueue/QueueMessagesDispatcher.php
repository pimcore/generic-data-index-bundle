<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Messenger\TransportName;
use Pimcore\Bundle\GenericDataIndexBundle\Message\DispatchQueueMessagesMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Model\Tool\TmpStore;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * @internal
 */
final class QueueMessagesDispatcher
{
    private const PENDING_TMP_STOREY_KEY = 'generic_data_index_queue_messages_dispatch_pending';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly IndexQueueRepository $indexQueueRepository,
    ) {
    }

    public function dispatchQueueMessages(bool $synchronously = false): void
    {
        if (!$synchronously && !$this->messageShouldBeTriggered()) {
            return;
        }

        $stamps = [];

        if ($synchronously) {
            $stamps[] = new TransportNamesStamp(TransportName::SYNC->value);
        }

        $message = new DispatchQueueMessagesMessage();
        $this->messageBus->dispatch($message, $stamps);

        $this->markAsPending();
    }

    public function messageShouldBeTriggered(): bool
    {
        return !$this->pendingMessageExists()
            && $this->indexQueueRepository->dispatchableItemExists();
    }

    public function pendingMessageExists(): bool
    {
        return TmpStore::get(self::PENDING_TMP_STOREY_KEY) !== null;
    }

    private function markAsPending(): void
    {
        $lifetime = 60*5; // 5 minutes
        TmpStore::add(self::PENDING_TMP_STOREY_KEY, true, null, $lifetime);
    }

    public function clearPendingState(): void
    {
        TmpStore::delete(self::PENDING_TMP_STOREY_KEY);
    }
}
