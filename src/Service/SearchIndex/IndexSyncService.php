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
use Symfony\Component\Lock\LockFactory;

/**
 * @internal
 */
final readonly class IndexSyncService implements IndexSyncServiceInterface
{
    private const LOCK_RESOURCE = 'generic-data-index:sync';

    private const LOCK_TTL_SECONDS = 3600.0;

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
                ->updateAll();

            $this->enqueueService->dispatchQueueMessages(false);

            $this->globalIndexAliasService->updateDataObjectAlias();
            $this->globalIndexAliasService->updateElementSearchAlias();
        } finally {
            $lock->release();
        }
    }
}
