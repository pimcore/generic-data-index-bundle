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

use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexSyncAlreadyRunningException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\GlobalIndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexSyncService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * @internal
 */
final class IndexSyncServiceTest extends Unit
{
    public function testSyncUpdatesWithoutRecreatingQueuesAndReleasesTheLock(): void
    {
        $calls = [];
        $indexUpdateService = $this->createMock(IndexUpdateServiceInterface::class);
        $indexUpdateService->expects($this->once())->method('setReCreateIndex')->with(false)
            ->willReturnCallback(function () use (&$calls, $indexUpdateService) {
                $calls[] = 'recreate-off';

                return $indexUpdateService;
            });
        $indexUpdateService->expects($this->once())->method('updateAll')
            ->willReturnCallback(function () use (&$calls, $indexUpdateService) {
                $calls[] = 'update';

                return $indexUpdateService;
            });

        $enqueueService = $this->createMock(EnqueueServiceInterface::class);
        $enqueueService->expects($this->once())->method('dispatchQueueMessages')
            ->with(false)
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'dispatch';
            });

        $aliasService = $this->createMock(GlobalIndexAliasServiceInterface::class);
        $aliasService->expects($this->once())->method('updateDataObjectAlias');
        $aliasService->expects($this->once())->method('updateElementSearchAlias');

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->createService($indexUpdateService, $enqueueService, $aliasService, $lock)->syncAll();

        $this->assertSame(['recreate-off', 'update', 'dispatch'], $calls);
    }

    public function testSyncFailsWhenAnotherSyncHoldsTheLock(): void
    {
        $indexUpdateService = $this->createMock(IndexUpdateServiceInterface::class);
        $indexUpdateService->expects($this->never())->method('updateAll');

        $enqueueService = $this->createMock(EnqueueServiceInterface::class);
        $enqueueService->expects($this->never())->method('dispatchQueueMessages');

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(false);
        $lock->expects($this->never())->method('release');

        $this->expectException(IndexSyncAlreadyRunningException::class);

        $this->createService(
            $indexUpdateService,
            $enqueueService,
            $this->createMock(GlobalIndexAliasServiceInterface::class),
            $lock
        )->syncAll();
    }

    public function testLockIsReleasedWhenTheUpdateFails(): void
    {
        $indexUpdateService = $this->createMock(IndexUpdateServiceInterface::class);
        $indexUpdateService->method('setReCreateIndex')->willReturnSelf();
        $indexUpdateService->method('updateAll')->willThrowException(new Exception('boom'));

        $enqueueService = $this->createMock(EnqueueServiceInterface::class);
        $enqueueService->expects($this->never())->method('dispatchQueueMessages');

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->expectExceptionMessage('boom');

        $this->createService(
            $indexUpdateService,
            $enqueueService,
            $this->createMock(GlobalIndexAliasServiceInterface::class),
            $lock
        )->syncAll();
    }

    private function createService(
        IndexUpdateServiceInterface $indexUpdateService,
        EnqueueServiceInterface $enqueueService,
        GlobalIndexAliasServiceInterface $aliasService,
        SharedLockInterface $lock,
    ): IndexSyncService {
        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        return new IndexSyncService($indexUpdateService, $enqueueService, $aliasService, $lockFactory);
    }
}
