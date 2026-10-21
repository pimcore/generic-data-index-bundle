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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexSyncAlreadyRunningException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\LockFactory;

/**
 * @internal
 */
final readonly class IndexSyncService implements IndexSyncServiceInterface
{
    private const LOCK_RESOURCE = 'generic-data-index:sync';

    /**
     * The lock is renewed right before and after every single index, so the lease only has to outlast the longest
     * single index operation (a native reindex waits up to an hour by default), not the whole synchronization.
     */
    private const LOCK_TTL_SECONDS = 7200.0;

    public function __construct(
        private IndexUpdateServiceInterface $indexUpdateService,
        private EnqueueServiceInterface $enqueueService,
        private GlobalIndexAliasServiceInterface $globalIndexAliasService,
        private LockFactory $lockFactory,
    ) {
    }

    public function syncAll(): void
    {
        $lock = $this->lockFactory->createLock(self::LOCK_RESOURCE, self::LOCK_TTL_SECONDS);
        if (!$lock->acquire()) {
            throw new IndexSyncAlreadyRunningException('An index synchronization is already running.');
        }

        try {
            $this->indexUpdateService
                ->setReCreateIndex(false)
                ->setIndexUpdateListener(static function () use ($lock): void {
                    $lock->refresh();
                })
                ->updateAll();

            $this->enqueueService->dispatchQueueMessages(false);

            $lock->refresh();
            $this->globalIndexAliasService->updateDataObjectAlias();
            $this->globalIndexAliasService->updateElementSearchAlias();
        } catch (LockConflictedException|LockExpiredException $exception) {
            throw new IndexSyncAlreadyRunningException(
                'The synchronization lock was lost, another index synchronization may be running.',
                0,
                $exception
            );
        } finally {
            $this->indexUpdateService->setIndexUpdateListener(null);
            $lock->release();
        }
    }
}
