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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Messenger\TransportName;
use Pimcore\Bundle\GenericDataIndexBundle\Message\DispatchQueueMessagesMessage;
use Pimcore\Model\Tool\TmpStore;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final class QueueMessagesDispatcher
{
    private const PENDING_TMP_STOREY_KEY = 'generic_data_index_queue_messages_dispatch_pending';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function dispatchQueueMessages(bool $synchronously = false): void
    {
        if (!$synchronously && $this->pendingMessageExists()) {
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

    private function pendingMessageExists(): bool
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
