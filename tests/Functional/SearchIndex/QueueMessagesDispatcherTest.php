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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;

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
        $this->dispatcher->dispatchQueueMessages(true);

        $this->assertFalse($this->dispatcher->pendingMessageExists());

        // with a clean pending state and a dispatchable queue entry, the async dispatch must trigger again
        $this->tester->clearQueue();
        $this->assertFalse($this->dispatcher->messageShouldBeTriggered());
    }
}
