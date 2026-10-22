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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex;

use Closure;
use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexSyncAlreadyRunningException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\GlobalIndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexSyncService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * @internal
 */
final class IndexSyncServiceTest extends Unit
{
    private const LOCK_TTL = 7200.0;

    public function testSyncUpdatesWithoutRecreatingQueuesAndReleasesTheLock(): void
    {
        $calls = [];
        $indexUpdateService = new FakeIndexUpdateService(static function (int $index) use (&$calls): void {
            $calls[] = 'index-' . $index;
        });

        $enqueueService = $this->createMock(EnqueueServiceInterface::class);
        $enqueueService->expects($this->once())->method('dispatchQueueMessages')
            ->with(false)
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'dispatch';
            });

        $aliasService = $this->createMock(GlobalIndexAliasServiceInterface::class);
        $aliasService->expects($this->once())->method('updateDataObjectAlias');
        $aliasService->expects($this->once())->method('updateElementSearchAlias');

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        (new IndexSyncService($indexUpdateService, $enqueueService, $aliasService, $lockFactory))->syncAll();

        $this->assertFalse($indexUpdateService->reCreateIndex);
        $this->assertSame(['index-1', 'index-2', 'index-3', 'dispatch'], $calls);
        $this->assertNull($indexUpdateService->listener, 'The listener must not outlive the synchronization');
    }

    public function testSyncFailsWhenAnotherSyncHoldsTheLock(): void
    {
        $indexUpdateService = new FakeIndexUpdateService(static function (): void {
        });

        $enqueueService = $this->createMock(EnqueueServiceInterface::class);
        $enqueueService->expects($this->never())->method('dispatchQueueMessages');

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(false);
        $lock->expects($this->never())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $this->expectException(IndexSyncAlreadyRunningException::class);

        try {
            (new IndexSyncService(
                $indexUpdateService,
                $enqueueService,
                $this->createMock(GlobalIndexAliasServiceInterface::class),
                $lockFactory
            ))->syncAll();
        } finally {
            $this->assertSame(0, $indexUpdateService->updatedIndices);
        }
    }

    public function testLockIsReleasedWhenTheUpdateFails(): void
    {
        $indexUpdateService = new FakeIndexUpdateService(static function (): void {
            throw new Exception('boom');
        });

        $enqueueService = $this->createMock(EnqueueServiceInterface::class);
        $enqueueService->expects($this->never())->method('dispatchQueueMessages');

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $this->expectExceptionMessage('boom');

        (new IndexSyncService(
            $indexUpdateService,
            $enqueueService,
            $this->createMock(GlobalIndexAliasServiceInterface::class),
            $lockFactory
        ))->syncAll();
    }

    public function testLockIsRenewedAroundEveryIndexSoALongSynchronizationStaysExclusive(): void
    {
        $now = 0.0;
        $factory = new LockFactory(new ExpiringTestLockStore(static function () use (&$now): float {
            return $now;
        }, self::LOCK_TTL));

        $secondAttempt = null;
        $second = null;
        $firstEnqueueService = $this->createMock(EnqueueServiceInterface::class);
        $firstEnqueueService->expects($this->once())->method('dispatchQueueMessages');

        // Three indices of 4000 seconds each: 12000 seconds in total, far beyond the 7200 seconds of the lease,
        // and already 8000 seconds (beyond the lease) when the third index is being updated.
        $first = new IndexSyncService(
            new FakeIndexUpdateService(function (int $index) use (&$now, &$secondAttempt, &$second): void {
                $now += 4000.0;
                if ($index === 3) {
                    try {
                        $second->syncAll();
                    } catch (IndexSyncAlreadyRunningException $exception) {
                        $secondAttempt = $exception;
                    }
                }
            }),
            $firstEnqueueService,
            $this->createMock(GlobalIndexAliasServiceInterface::class),
            $factory
        );

        $secondUpdateService = new FakeIndexUpdateService(static function (): void {
        });
        $second = new IndexSyncService(
            $secondUpdateService,
            $this->createMock(EnqueueServiceInterface::class),
            $this->createMock(GlobalIndexAliasServiceInterface::class),
            $factory
        );

        $first->syncAll();

        $this->assertInstanceOf(IndexSyncAlreadyRunningException::class, $secondAttempt);
        $this->assertSame(0, $secondUpdateService->updatedIndices, 'The second synchronization must not start');
    }

    public function testSyncStopsWhenTheLockWasTakenOverAfterItExpired(): void
    {
        $now = 0.0;
        $factory = new LockFactory(new ExpiringTestLockStore(static function () use (&$now): float {
            return $now;
        }, self::LOCK_TTL));

        $enqueueService = $this->createMock(EnqueueServiceInterface::class);
        $enqueueService->expects($this->never())->method('dispatchQueueMessages');
        $aliasService = $this->createMock(GlobalIndexAliasServiceInterface::class);
        $aliasService->expects($this->never())->method('updateDataObjectAlias');

        $takeOver = null;
        $indexUpdateService = new FakeIndexUpdateService(function (int $index) use (&$now, $factory, &$takeOver): void {
            if ($index === 2) {
                // A single index runs longer than the lease: another process gets the lock in the meantime
                $now += self::LOCK_TTL + 1;
                $takeOver = $factory->createLock('generic-data-index:sync', self::LOCK_TTL);
                $takeOver->acquire();
            }
        });

        try {
            (new IndexSyncService($indexUpdateService, $enqueueService, $aliasService, $factory))->syncAll();
            $this->fail('The synchronization must stop when it lost the lock');
        } catch (IndexSyncAlreadyRunningException) {
            $this->assertSame(2, $indexUpdateService->updatedIndices, 'The third index must not be updated');
            $this->assertNotNull($takeOver);
            $this->assertTrue($takeOver->isAcquired(), 'The lock of the other process must stay intact');
        }
    }
}

/**
 * @internal
 */
final class FakeIndexUpdateService implements IndexUpdateServiceInterface
{
    public bool $reCreateIndex = true;

    public ?Closure $listener = null;

    public int $updatedIndices = 0;

    private const INDICES = 3;

    public function __construct(private readonly Closure $work)
    {
    }

    public function updateAll(): self
    {
        for ($index = 1; $index <= self::INDICES; $index++) {
            $this->notify();
            ($this->work)($index);
            $this->updatedIndices = $index;
            $this->notify();
        }

        return $this;
    }

    public function updateClassDefinitions(): self
    {
        return $this;
    }

    public function updateClassDefinition(ClassDefinition $classDefinition): self
    {
        return $this;
    }

    public function updateAssets(): self
    {
        return $this;
    }

    public function updateDocuments(): self
    {
        return $this;
    }

    public function setReCreateIndex(bool $reCreateIndex): self
    {
        $this->reCreateIndex = $reCreateIndex;

        return $this;
    }

    public function setIndexUpdateListener(?callable $listener): self
    {
        $this->listener = $listener === null ? null : Closure::fromCallable($listener);

        return $this;
    }

    private function notify(): void
    {
        if ($this->listener !== null) {
            ($this->listener)();
        }
    }
}

/**
 * A lock store that expires locks against a controllable clock, like Redis or the database store do.
 *
 * @internal
 */
final class ExpiringTestLockStore implements PersistingStoreInterface
{
    /** @var array<string, array{token: string, expiresAt: float}> */
    private array $locks = [];

    private int $tokenCounter = 0;

    public function __construct(private readonly Closure $clock, private readonly float $defaultTtl)
    {
    }

    public function save(Key $key): void
    {
        $name = (string) $key;
        if ($this->heldByAnother($key)) {
            throw new LockConflictedException();
        }

        if (!$key->hasState(self::class)) {
            $key->setState(self::class, 'token-' . ++$this->tokenCounter);
        }

        $this->locks[$name] = ['token' => $key->getState(self::class), 'expiresAt' => $this->now() + $this->defaultTtl];
    }

    public function delete(Key $key): void
    {
        if ($this->exists($key)) {
            unset($this->locks[(string) $key]);
        }
    }

    public function exists(Key $key): bool
    {
        $name = (string) $key;

        return isset($this->locks[$name])
            && $this->locks[$name]['expiresAt'] > $this->now()
            && $key->hasState(self::class)
            && $this->locks[$name]['token'] === $key->getState(self::class);
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
        $name = (string) $key;
        if ($this->heldByAnother($key)) {
            throw new LockConflictedException();
        }

        if (!$this->exists($key)) {
            throw new LockExpiredException();
        }

        $this->locks[$name]['expiresAt'] = $this->now() + $ttl;
    }

    private function heldByAnother(Key $key): bool
    {
        $name = (string) $key;

        return isset($this->locks[$name])
            && $this->locks[$name]['expiresAt'] > $this->now()
            && (!$key->hasState(self::class) || $this->locks[$name]['token'] !== $key->getState(self::class));
    }

    private function now(): float
    {
        return ($this->clock)();
    }
}
