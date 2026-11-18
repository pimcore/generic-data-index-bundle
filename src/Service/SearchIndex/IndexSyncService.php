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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\IndexLockException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexSyncAlreadyRunningException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexLockInterface;
use Symfony\Component\Lock\Exception\LockConflictedException;

/**
 * The synchronization of all indices holds one lock across the updates of the single indices, like every other
 * update of all indices. It is an index lock, so its lifetime covers the longest copy attempt of the configured
 * reindex settings, and it is renewed before every index and by every retry of a copy inside an index, together
 * with the locks of the single indices.
 *
 * @internal
 */
final readonly class IndexSyncService implements IndexSyncServiceInterface
{
    private const LOCK_NAME = 'index-synchronization';

    public function __construct(
        private IndexUpdateServiceInterface $indexUpdateService,
        private EnqueueServiceInterface $enqueueService,
        private GlobalIndexAliasServiceInterface $globalIndexAliasService,
        private IndexLockInterface $indexLock,
    ) {
    }

    public function syncAll(): void
    {
        try {
            $started = $this->indexLock->runIfFree(self::LOCK_NAME, function (): void {
                $this->synchronize();
            });
        } catch (IndexLockException $exception) {
            if ($exception->getPrevious() instanceof LockConflictedException) {
                throw new IndexSyncAlreadyRunningException(
                    'The synchronization stopped because another update took a lock over: ' . $exception->getMessage(),
                    0,
                    $exception
                );
            }

            throw $exception;
        }

        if (!$started) {
            throw new IndexSyncAlreadyRunningException('An index synchronization is already running.');
        }
    }

    private function synchronize(): void
    {
        $keepLockAlive = function (): void {
            $this->indexLock->refresh(self::LOCK_NAME);
        };

        try {
            $this->indexUpdateService
                ->setReCreateIndex(false)
                ->setIndexUpdateListener($keepLockAlive)
                ->updateAll();

            $this->enqueueService->dispatchQueueMessages(false);

            $keepLockAlive();
            $this->globalIndexAliasService->updateDataObjectAlias();
            $this->globalIndexAliasService->updateElementSearchAlias();
        } finally {
            $this->indexUpdateService->setIndexUpdateListener(null);
        }
    }
}
