<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Functional\SearchIndex;

use Codeception\Test\Unit;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Db;
use Pimcore\Tests\Support\Util\TestHelper;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class QueueMessagesDispatcherTest extends Unit
{
    protected IndexTester $tester;

    private QueueMessagesDispatcher $dispatcher;

    protected function _before(): void
    {
        $this->dispatcher = $this->tester->grabService(QueueMessagesDispatcher::class);
        $this->dispatcher->clearPendingState();
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
        $this->tester->clearQueue();
        $this->dispatcher->clearPendingState();
    }

    public function testSynchronousDispatchDoesNotLeaveStalePendingFlag(): void
    {
        $this->dispatcher->dispatchQueueMessages(true);

        $this->assertFalse(
            $this->dispatcher->pendingMessageExists(),
            'A synchronously dispatched queue message is handled inline, so no pending flag may remain afterwards.'
            . ' A stale flag suppresses asynchronous queue dispatching for the whole TmpStore lifetime.'
        );
    }

    public function testAsynchronousDispatchIsPossibleAgainAfterSynchronousDispatch(): void
    {
        $this->tester->clearQueue();
        $this->dispatcher->dispatchQueueMessages(true);

        $this->assertFalse($this->dispatcher->pendingMessageExists());

        // an element saved after the synchronous dispatch cycle enqueues a dispatchable
        // entry, and its save event must actually dispatch the next asynchronous
        // DispatchQueueMessagesMessage to the transport instead of being suppressed
        // by a stale pending flag
        $messagesBefore = $this->countQueueTransportMessages();
        $this->tester->disableSynchronousProcessing();
        TestHelper::createImageAsset();

        $this->assertGreaterThan(
            $messagesBefore,
            $this->countQueueTransportMessages(),
            'Saving an element after a synchronous dispatch must dispatch the next asynchronous'
            . ' queue message instead of being suppressed by a stale pending flag.'
        );
    }

    public function testFailedDispatchClearsPendingFlagAndRethrows(): void
    {
        $failingBus = new class () implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw new RuntimeException('dispatch failed');
            }
        };

        $dispatcher = new QueueMessagesDispatcher(
            $failingBus,
            $this->tester->grabService(IndexQueueRepository::class),
        );
        $dispatcher->clearPendingState();

        $thrown = null;

        try {
            $dispatcher->dispatchQueueMessages(true);
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        }

        $this->assertNotNull($thrown, 'The dispatch exception must be rethrown.');
        $this->assertSame('dispatch failed', $thrown->getMessage());
        $this->assertFalse(
            $dispatcher->pendingMessageExists(),
            'A failed dispatch must not leave the pending flag behind.'
        );
    }

    private function countQueueTransportMessages(): int
    {
        try {
            return (int) Db::get()->fetchOne(
                "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'pimcore_generic_data_index_queue'"
            );
        } catch (TableNotFoundException) {
            return 0;
        }
    }
}
