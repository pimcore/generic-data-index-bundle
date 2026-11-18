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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Snapshot;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotLockException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotLock;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

final class SnapshotLockTest extends Unit
{
    public function testRefreshRetriesOnceAfterATransientStoreFailure(): void
    {
        $store = new FailingRefreshLockStore();
        $lockFactory = new LockFactory($store);
        $lock = SnapshotLock::create($lockFactory);
        $this->assertTrue($lock->acquire());
        $store->failNextRefreshes(1);

        SnapshotLock::refresh($lock);

        $this->assertSame(2, $store->getRefreshCalls());
        $this->assertTrue($lock->isAcquired());
        $this->assertFalse(SnapshotLock::create($lockFactory)->acquire(), 'the lock is still held');
    }

    public function testRefreshFailingTwiceThrowsWithTheUnderlyingCause(): void
    {
        $store = new FailingRefreshLockStore();
        $lock = SnapshotLock::create(new LockFactory($store));
        $this->assertTrue($lock->acquire());
        $store->failNextRefreshes(2);

        try {
            SnapshotLock::refresh($lock);
            $this->fail('a persistent store failure must not be swallowed');
        } catch (SnapshotLockException $e) {
            $this->assertStringContainsString('MySQL server has gone away', $e->getMessage());
        }
        $this->assertSame(2, $store->getRefreshCalls());
    }

    public function testRefreshDoesNotRetryWhenAnotherRunTookTheLock(): void
    {
        $conflict = new LockConflictedException();
        $lock = $this->makeEmpty(LockInterface::class, [
            'refresh' => Expected::once(static function () use ($conflict): never {
                throw $conflict;
            }),
        ]);

        try {
            SnapshotLock::refresh($lock);
            $this->fail('a lost lock must abort the run');
        } catch (LockConflictedException $e) {
            $this->assertSame($conflict, $e);
        }
    }
}
