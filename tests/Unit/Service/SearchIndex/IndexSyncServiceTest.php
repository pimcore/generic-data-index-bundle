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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\IndexLockException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexSyncAlreadyRunningException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\GlobalIndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexLock;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexSyncService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * @internal
 */
final class IndexSyncServiceTest extends Unit
{
    private const LOCK_RESOURCE = 'generic-data-index:mapping-update:index-synchronization';

    /**
     * The lifetime of an index lock for 100 polls of 10 seconds: that wait, 2 × 3 cancellation polls, and a quarter
     * of the attempt on top.
     */
    private const LOCK_TTL = 1325.0;

    private float $now = 0.0;

    private ExpiringTestLockStore $store;

    private LockFactory $lockFactory;

    private IndexLock $indexLock;

    protected function _before(): void
    {
        $this->now = 0.0;
        $this->store = new ExpiringTestLockStore(fn (): float => $this->now, self::LOCK_TTL);
        $this->lockFactory = new LockFactory($this->store);
        $this->indexLock = new IndexLock($this->lockFactory, 100, 10);
    }

    public function testSyncUpdatesWithoutRecreatingQueuesAndReleasesTheLock(): void
    {
        $calls = [];
        $heldDuringTheUpdate = null;
        $indexUpdateService = new FakeIndexUpdateService(function (int $index) use (&$calls, &$heldDuringTheUpdate): void {
            $calls[] = 'index-' . $index;
            $heldDuringTheUpdate = $this->store->heldCount();
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

        $this->createService($indexUpdateService, $enqueueService, $aliasService)->syncAll();

        $this->assertFalse($indexUpdateService->reCreateIndex);
        $this->assertSame(['index-1', 'index-2', 'index-3', 'dispatch'], $calls);
        $this->assertSame(1, $heldDuringTheUpdate, 'The synchronization lock is held while the indices are updated');
        $this->assertSame(0, $this->store->heldCount(), 'The lock is released afterwards');
        $this->assertNull($indexUpdateService->listener, 'The listener must not outlive the synchronization');
    }

    public function testASecondSynchronizationFailsWhileTheFirstOneRuns(): void
    {
        $second = null;
        $secondUpdateService = new FakeIndexUpdateService(static function (): void {
        });
        $failure = null;
        $first = $this->createService(new FakeIndexUpdateService(function (int $index) use (&$second, &$failure): void {
            if ($index === 1) {
                try {
                    $second->syncAll();
                } catch (IndexSyncAlreadyRunningException $exception) {
                    $failure = $exception;
                }
            }
        }));
        $second = $this->createService($secondUpdateService);

        $first->syncAll();

        $this->assertInstanceOf(IndexSyncAlreadyRunningException::class, $failure);
        $this->assertSame(0, $secondUpdateService->updatedIndices, 'The second synchronization must not start');
        $this->assertSame(0, $this->store->heldCount());
    }

    public function testLockIsReleasedWhenTheUpdateFails(): void
    {
        $enqueueService = $this->createMock(EnqueueServiceInterface::class);
        $enqueueService->expects($this->never())->method('dispatchQueueMessages');

        $service = $this->createService(
            new FakeIndexUpdateService(static function (): void {
                throw new Exception('boom');
            }),
            $enqueueService
        );

        try {
            $service->syncAll();
            $this->fail('The failure of the update must propagate');
        } catch (Exception $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertSame(0, $this->store->heldCount(), 'The lock must be released');
    }

    public function testLockIsRenewedAroundEveryIndexSoALongSynchronizationStaysExclusive(): void
    {
        $secondAttempt = null;
        $second = null;
        $secondUpdateService = new FakeIndexUpdateService(static function (): void {
        });

        $firstEnqueueService = $this->createMock(EnqueueServiceInterface::class);
        $firstEnqueueService->expects($this->once())->method('dispatchQueueMessages');

        // Three indices of 80 % of the lifetime each: 2.4 times the lifetime in total, and already 1.6 times when
        // the third index is being updated.
        $first = $this->createService(
            new FakeIndexUpdateService(function (int $index) use (&$secondAttempt, &$second): void {
                $this->now += 0.8 * self::LOCK_TTL;
                if ($index === 3) {
                    try {
                        $second->syncAll();
                    } catch (IndexSyncAlreadyRunningException $exception) {
                        $secondAttempt = $exception;
                    }
                }
            }),
            $firstEnqueueService
        );
        $second = $this->createService($secondUpdateService);

        $first->syncAll();

        $this->assertInstanceOf(IndexSyncAlreadyRunningException::class, $secondAttempt);
        $this->assertSame(0, $secondUpdateService->updatedIndices, 'The second synchronization must not start');
    }

    /**
     * With the "copy" strategy one index is copied in up to three attempts, each of which can take as long as a
     * full copy. The copy service renews the held locks before every further attempt; the synchronization lock has
     * to be among them, or another synchronization takes it over in the middle of the index.
     */
    public function testLockIsRenewedByTheCopyAttemptsInsideOneIndex(): void
    {
        $secondAttempt = null;
        $second = null;
        $secondUpdateService = new FakeIndexUpdateService(static function (): void {
        });

        $first = $this->createService(
            new FakeIndexUpdateService(function (int $index) use (&$secondAttempt, &$second): void {
                if ($index !== 2) {
                    return;
                }

                $this->now += 0.8 * self::LOCK_TTL;
                $this->indexLock->refresh('pimcore_class_example');
                $this->now += 0.8 * self::LOCK_TTL;

                try {
                    $second->syncAll();
                } catch (IndexSyncAlreadyRunningException $exception) {
                    $secondAttempt = $exception;
                }
            })
        );
        $second = $this->createService($secondUpdateService);

        $first->syncAll();

        $this->assertInstanceOf(
            IndexSyncAlreadyRunningException::class,
            $secondAttempt,
            'After 1.6 times the lifetime inside one index the lock must still be held'
        );
        $this->assertSame(0, $secondUpdateService->updatedIndices);
    }

    public function testSyncStopsWhenTheLockWasTakenOverAfterItExpired(): void
    {
        $enqueueService = $this->createMock(EnqueueServiceInterface::class);
        $enqueueService->expects($this->never())->method('dispatchQueueMessages');
        $aliasService = $this->createMock(GlobalIndexAliasServiceInterface::class);
        $aliasService->expects($this->never())->method('updateDataObjectAlias');

        $takeOver = null;
        $indexUpdateService = new FakeIndexUpdateService(function (int $index) use (&$takeOver): void {
            if ($index === 2) {
                // A single index runs longer than the lease: another process gets the lock in the meantime
                $this->now += self::LOCK_TTL + 1;
                $takeOver = $this->lockFactory->createLock(self::LOCK_RESOURCE, self::LOCK_TTL);
                $takeOver->acquire();
            }
        });

        try {
            $this->createService($indexUpdateService, $enqueueService, $aliasService)->syncAll();
            $this->fail('The synchronization must stop when it lost the lock');
        } catch (IndexSyncAlreadyRunningException $exception) {
            $this->assertInstanceOf(IndexLockException::class, $exception->getPrevious());
            $this->assertSame(2, $indexUpdateService->updatedIndices, 'The third index must not be updated');
            $this->assertNotNull($takeOver);
            $this->assertTrue($takeOver->isAcquired(), 'The lock of the other process must stay intact');
        }
    }

    public function testSyncStopsWhenTheLockExpiredWithoutAnyoneTakingItOver(): void
    {
        $enqueueService = $this->createMock(EnqueueServiceInterface::class);
        $enqueueService->expects($this->never())->method('dispatchQueueMessages');

        $indexUpdateService = new FakeIndexUpdateService(function (int $index): void {
            if ($index === 2) {
                $this->now += self::LOCK_TTL + 1;
            }
        });

        try {
            $this->createService($indexUpdateService, $enqueueService)->syncAll();
            $this->fail('The synchronization must stop when its lock expired');
        } catch (IndexLockException $exception) {
            $this->assertStringContainsString('could not be refreshed', $exception->getMessage());
            $this->assertSame(2, $indexUpdateService->updatedIndices, 'The third index must not be updated');
        }
    }

    private function createService(
        IndexUpdateServiceInterface $indexUpdateService,
        ?EnqueueServiceInterface $enqueueService = null,
        ?GlobalIndexAliasServiceInterface $aliasService = null,
    ): IndexSyncService {
        return new IndexSyncService(
            $indexUpdateService,
            $enqueueService ?? $this->createMock(EnqueueServiceInterface::class),
            $aliasService ?? $this->createMock(GlobalIndexAliasServiceInterface::class),
            $this->indexLock
        );
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

    public function heldCount(): int
    {
        return count(array_filter(
            $this->locks,
            fn (array $lock): bool => $lock['expiresAt'] > $this->now()
        ));
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
