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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotLockException;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * The one lock that makes snapshot export and import mutually exclusive: a concurrent import
 * could read a half-written snapshot, an export could page an alias the import is recreating,
 * and both race the class-mapping checksum stamping the export/import cycle depends on.
 *
 * The lock comes from the installation's lock store (framework.lock), not from a host-local
 * file lock, so with a shared store (Redis, database) it also excludes runs on other nodes.
 * The TTL only matters for stores that expire keys: a run that crashes hard leaves its lock
 * behind for at most that long, while a healthy run refreshes it after every index.
 *
 * A refresh is retried once: the default (DBAL) store's connection sits idle while one large
 * index is exported or imported, so the server's wait_timeout may have closed it by the time
 * the refresh runs. DBAL drops a lost connection and reconnects on the next query, so the
 * retry goes through on a fresh connection.
 *
 * @internal
 */
final class SnapshotLock
{
    private const RESOURCE = 'generic-data-index:snapshot';

    private const TTL_SECONDS = 86400.0;

    public static function create(LockFactory $lockFactory): LockInterface
    {
        return $lockFactory->createLock(self::RESOURCE, self::TTL_SECONDS);
    }

    /**
     * A LockConflictedException (another run took the lock over) is not retried: it must abort.
     *
     * @throws SnapshotLockException when the refresh fails twice
     */
    public static function refresh(LockInterface $lock): void
    {
        try {
            $lock->refresh();
        } catch (LockAcquiringException) {
            self::refreshAgain($lock);
        }
    }

    private static function refreshAgain(LockInterface $lock): void
    {
        try {
            $lock->refresh();
        } catch (LockAcquiringException $e) {
            throw new SnapshotLockException(
                sprintf(
                    'Failed to refresh the snapshot lock: %s',
                    $e->getPrevious()?->getMessage() ?? $e->getMessage(),
                ),
                0,
                $e,
            );
        }
    }
}
