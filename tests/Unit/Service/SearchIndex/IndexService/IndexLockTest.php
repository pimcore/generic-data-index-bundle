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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexLock;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;

/**
 * @internal
 */
final class IndexLockTest extends Unit
{
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

    public function testAFailedRefreshIsLoggedAndTheUpdateGoesOn(): void
    {
        $store = new RecordingLockStore();
        $lock = $this->createIndexLock($store);
        $lock->setLogger($this->makeEmpty(LoggerInterface::class, [
            'warning' => Expected::once(function (string $message): void {
                $this->assertStringContainsString('could not be refreshed', $message);
            }),
        ]));
        $completed = false;

        $lock->run(self::ALIAS, static function () use ($lock, $store, &$completed): void {
            $store->conflictOnNextRefreshes(1);
            $lock->refresh(self::ALIAS);
            $completed = true;
        });

        $this->assertTrue($completed);
    }

    public function testTheLockOutlivesTheLongestWaitForACopyTask(): void
    {
        $store = new RecordingLockStore();
        $lock = new IndexLock(new LockFactory($store), 720, 5);

        $lock->run(self::ALIAS, static fn () => null);

        $this->assertSame(3660.0, $store->getLastTtl(), '720 polls of 5 seconds plus a margin of 60 seconds');
    }

    private function createIndexLock(RecordingLockStore $store): IndexLock
    {
        return new IndexLock(new LockFactory($store), 1, 1);
    }
}
