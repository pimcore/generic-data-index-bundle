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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\IndexService;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\IndexLockException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexLock;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;

/**
 * @internal
 */
final class IndexLockTest extends Unit
{
    private const RESOURCE_PREFIX = 'generic-data-index:mapping-update:';

    private const ALIAS = 'test_alias';

    private const RESOURCE = 'generic-data-index:mapping-update:test_alias';

    public function testTheOperationRunsUnderTheLockOfTheAliasWhichIsReleasedAfterwards(): void
    {
        $store = new RecordingLockStore();
        $lock = $this->createIndexLock($store);
        $heldDuringOperation = null;

        $result = $lock->run(self::ALIAS, static function () use ($store, &$heldDuringOperation): string {
            $heldDuringOperation = $store->getHeldResources();

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame([self::RESOURCE], $heldDuringOperation);
        $this->assertSame([], $store->getHeldResources());
        $this->assertSame(1, $store->getSaves());
        $this->assertSame(1, $store->getDeletes());
    }

    public function testTheOperationWaitsUntilAnotherProcessReleasesTheLock(): void
    {
        $store = new RecordingLockStore();
        $store->conflictOnNextSaves(2);
        $lock = $this->createIndexLock($store);
        $lock->setLogger($this->makeEmpty(LoggerInterface::class, [
            'notice' => Expected::once(function (string $message): void {
                $this->assertStringContainsString('Waiting for another mapping update of "test_alias"', $message);
            }),
        ]));
        $runs = 0;

        $lock->run(self::ALIAS, static function () use (&$runs): void {
            ++$runs;
        });

        $this->assertSame(1, $runs);
        $this->assertSame(3, $store->getSaves(), 'two attempts find the lock taken, the third takes it');
        $this->assertSame([], $store->getHeldResources());
    }

    public function testNestedRunsForTheSameAliasShareTheLock(): void
    {
        $store = new RecordingLockStore();
        $lock = $this->createIndexLock($store);

        $heldInside = $lock->run(
            self::ALIAS,
            static fn () => $lock->run(self::ALIAS, static fn () => $store->getHeldResources())
        );

        $this->assertSame([self::RESOURCE], $heldInside);
        $this->assertSame(1, $store->getSaves());
        $this->assertSame([], $store->getHeldResources(), 'the outer run releases the lock');
    }

    public function testRunsForDifferentAliasesTakeSeparateLocks(): void
    {
        $store = new RecordingLockStore();
        $lock = $this->createIndexLock($store);

        $heldInside = $lock->run(
            'first',
            static fn () => $lock->run('second', static fn () => $store->getHeldResources())
        );

        $this->assertSame(
            ['generic-data-index:mapping-update:first', 'generic-data-index:mapping-update:second'],
            $heldInside
        );
        $this->assertSame([], $store->getHeldResources());
    }

    public function testTheLockIsReleasedWhenTheOperationThrows(): void
    {
        $store = new RecordingLockStore();
        $lock = $this->createIndexLock($store);

        try {
            $lock->run(self::ALIAS, static function (): void {
                throw new RuntimeException('copy failed');
            });
            $this->fail('the exception of the operation must propagate');
        } catch (RuntimeException $e) {
            $this->assertSame('copy failed', $e->getMessage());
        }

        $this->assertSame([], $store->getHeldResources());
        $this->assertSame(1, $store->getDeletes());
    }

    public function testAFailingLockStoreThrowsAnIndexLockException(): void
    {
        $store = new RecordingLockStore();
        $store->failSavesWith(new RuntimeException('MySQL server has gone away'));
        $lock = $this->createIndexLock($store);

        $this->expectException(IndexLockException::class);
        $this->expectExceptionMessage('Taking the lock of "test_alias" failed');

        $lock->run(self::ALIAS, static fn () => null);
    }

    public function testAReleaseFailureIsLoggedAndTheResultIsReturned(): void
    {
        $store = new RecordingLockStore();
        $store->keepKeysAfterDelete();
        $lock = $this->createIndexLock($store);
        $lock->setLogger($this->makeEmpty(LoggerInterface::class, [
            'warning' => Expected::once(function (string $message): void {
                $this->assertStringContainsString('was not released cleanly', $message);
            }),
        ]));

        $result = $lock->run(self::ALIAS, static fn () => 'done');

        $this->assertSame('done', $result);
    }

    public function testRefreshExtendsTheHeldLock(): void
    {
        $store = new RecordingLockStore();
        $lock = $this->createIndexLock($store);

        $lock->run(self::ALIAS, function () use ($lock, $store): void {
            $refreshesAfterAcquire = $store->getRefreshes();

            $lock->refresh(self::ALIAS);

            $this->assertSame($refreshesAfterAcquire + 1, $store->getRefreshes());
        });
    }

    public function testRefreshWithoutAHeldLockDoesNothing(): void
    {
        $store = new RecordingLockStore();

        $this->createIndexLock($store)->refresh(self::ALIAS);

        $this->assertSame(0, $store->getRefreshes());
    }

    public function testALockTakenOverByAnotherProcessStopsTheUpdate(): void
    {
        $store = new RecordingLockStore();
        $lock = $this->createIndexLock($store);

        try {
            $lock->run(self::ALIAS, static function () use ($lock, $store): void {
                $store->conflictOnNextRefreshes(1);
                $lock->refresh(self::ALIAS);
            });
            $this->fail('an update without its lock must stop');
        } catch (IndexLockException $e) {
            $this->assertStringContainsString('taken over by another mapping update', $e->getMessage());
        }

        $this->assertSame([], $store->getHeldResources());
    }

    public function testATransientRefreshFailureIsRetriedOnce(): void
    {
        $store = new RecordingLockStore();
        $lock = $this->createIndexLock($store);
        $completed = false;

        $lock->run(self::ALIAS, function () use ($lock, $store, &$completed): void {
            $store->failNextRefreshesWith(new RuntimeException('MySQL server has gone away'));
            $refreshesBefore = $store->getRefreshes();

            $lock->refresh(self::ALIAS);

            $this->assertSame($refreshesBefore + 2, $store->getRefreshes(), 'the failed refresh is repeated');
            $completed = true;
        });

        $this->assertTrue($completed);
    }

    public function testARefreshFailingTwiceStopsTheUpdate(): void
    {
        $store = new RecordingLockStore();
        $lock = $this->createIndexLock($store);

        try {
            $lock->run(self::ALIAS, static function () use ($lock, $store): void {
                $store->failNextRefreshesWith(new RuntimeException('MySQL server has gone away'), 2);
                $lock->refresh(self::ALIAS);
            });
            $this->fail('a persistent store failure must not be swallowed');
        } catch (IndexLockException $e) {
            $this->assertStringContainsString('could not be refreshed', $e->getMessage());
            $this->assertStringContainsString('MySQL server has gone away', $e->getMessage());
        }

        $this->assertSame([], $store->getHeldResources());
    }

    public function testTheLockOutlivesTheLongestWaitForACopyTask(): void
    {
        $store = new RecordingLockStore();
        $lock = new IndexLock(new LockFactory($store), 720, 5);

        $lock->run(self::ALIAS, static fn () => null);

        $this->assertSame(
            4537.0,
            $store->getLastTtl(),
            '720 polls and 2 × 3 cancellation polls of 5 seconds, plus a quarter of that attempt'
        );
    }

    public function testTheLockOutlivesAShortCopyWaitByAtLeastAMinute(): void
    {
        $store = new RecordingLockStore();
        $lock = new IndexLock(new LockFactory($store), 12, 5);

        $lock->run(self::ALIAS, static fn () => null);

        $this->assertSame(
            150.0,
            $store->getLastTtl(),
            '12 polls and 2 × 3 cancellation polls of 5 seconds, plus the least margin of 60 seconds'
        );
    }

    /**
     * A copy attempt is not only the wait for its task: before it, a leftover task of an aborted attempt is
     * cancelled, and on a timeout its own one; each cancellation waits for up to REINDEX_CANCEL_POLLS polls. A lock
     * that covers only the task wait expires during the cancellation, and a second update takes it over while the
     * first still deletes and switches indices.
     */
    public function testTheLockOutlivesTheCancellationsOfACopyAttempt(): void
    {
        $store = new RecordingLockStore();
        $lock = new IndexLock(new LockFactory($store), 1, 120);

        $lock->run(self::ALIAS, static fn () => null);

        $cancellations = 2 * SearchIndexServiceInterface::REINDEX_CANCEL_POLLS * 120;
        $this->assertGreaterThan(120 + $cancellations, $store->getLastTtl());
        $this->assertSame(1050.0, $store->getLastTtl(), '7 polls of 120 seconds plus a quarter of that attempt');
    }

    /**
     * An update of all indices (the language change) holds one lock across the updates of the single indices. The
     * wait for an index lock and the attempts inside it renew that outer lock too, so it outlives them.
     */
    public function testANestedLockRenewsTheLocksAlreadyHeld(): void
    {
        $store = new RecordingLockStore();
        $lock = $this->createIndexLock($store);

        $lock->run('languages', static fn () => $lock->run('pimcore_class', static fn () => null));

        // Taking a lock with a lifetime renews it once itself; the outer lock is renewed again by the nested one.
        $this->assertSame(2, $store->getRefreshesOf(self::RESOURCE_PREFIX . 'languages'));
        $this->assertSame(1, $store->getRefreshesOf(self::RESOURCE_PREFIX . 'pimcore_class'));
    }

    public function testRefreshRenewsEveryHeldLock(): void
    {
        $store = new RecordingLockStore();
        $lock = $this->createIndexLock($store);

        $lock->run('languages', static function () use ($lock): void {
            $lock->run('pimcore_class', static fn () => $lock->refresh('pimcore_class'));
        });

        $this->assertSame(3, $store->getRefreshesOf(self::RESOURCE_PREFIX . 'languages'));
        $this->assertSame(2, $store->getRefreshesOf(self::RESOURCE_PREFIX . 'pimcore_class'));
    }

    public function testALockTakenOverWhileWaitingForANestedOneStopsTheUpdate(): void
    {
        $store = new RecordingLockStore();
        $lock = $this->createIndexLock($store);
        $ran = false;

        try {
            $lock->run('languages', static function () use ($lock, $store, &$ran): void {
                // Another language update takes the outer lock over while this one waits for the index lock.
                $store->conflictOnNextRefreshOf(self::RESOURCE_PREFIX . 'languages');
                $lock->run('pimcore_class', static function () use (&$ran): void {
                    $ran = true;
                });
            });
        } catch (IndexLockException $e) {
            $this->assertStringContainsString('"languages"', $e->getMessage());
            $this->assertFalse($ran, 'The index is not changed under a lock another update took over');
            $this->assertSame([], $store->getHeldResources(), 'Both locks are released');

            return;
        }

        $this->fail('A lock taken over during the wait for a nested lock must stop the update');
    }

    /**
     * A PHP fatal error - the OOM a large classification store mapping produced - does not run "finally", so the
     * lock was left held for its full TTL: 75.6 minutes with the shipped reindex settings, during which every
     * further mapping update of that index waits with one NOTICE to show for it. A shutdown release is the one
     * hook a fatal still runs.
     */
    public function testAFatalDuringTheOperationReleasesTheLockThroughTheShutdownHook(): void
    {
        $store = new RecordingLockStore();
        $registered = [];
        $lock = new IndexLock(
            new LockFactory($store),
            1,
            1,
            static function (callable $release) use (&$registered): void {
                $registered[] = $release;
            }
        );

        $lock->run(self::ALIAS, function () use ($store, &$registered): void {
            $this->assertCount(1, $registered, 'A shutdown release must be registered while a lock is held');
            $this->assertSame([self::RESOURCE], $store->getHeldResources());

            // what a fatal error leaves of the operation: no "finally", only the shutdown functions
            ($registered[0])();

            $this->assertSame(
                [],
                $store->getHeldResources(),
                'The lock of an index whose mapping update died must not stay held for its TTL'
            );
        });
    }

    public function testTheShutdownHookReleasesNothingAfterANormalEnd(): void
    {
        $store = new RecordingLockStore();
        $registered = [];
        $lock = new IndexLock(
            new LockFactory($store),
            1,
            1,
            static function (callable $release) use (&$registered): void {
                $registered[] = $release;
            }
        );

        $lock->run(self::ALIAS, static function (): void {
        });

        $deletesAfterRun = $store->getDeletes();
        ($registered[0])();

        $this->assertSame($deletesAfterRun, $store->getDeletes(), 'Nothing is left to release after a clean run');
    }

    public function testTheShutdownReleaseIsRegisteredOnlyOnce(): void
    {
        $store = new RecordingLockStore();
        $registrations = 0;
        $lock = new IndexLock(
            new LockFactory($store),
            1,
            1,
            static function (callable $release) use (&$registrations): void {
                ++$registrations;
            }
        );

        $lock->run(self::ALIAS, static function (): void {
        });
        $lock->run(self::ALIAS, static function (): void {
        });

        $this->assertSame(1, $registrations, 'A worker process must not pile up a shutdown function per update');
    }

    private function createIndexLock(RecordingLockStore $store): IndexLock
    {
        return new IndexLock(new LockFactory($store), 1, 1);
    }
}
