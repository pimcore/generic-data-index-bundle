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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService;

use Closure;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\IndexLockException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\Exception\ExceptionInterface as LockExceptionInterface;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * The lock is taken from the lock store of the application. Pimcore configures the database store, so the
 * messenger workers and the console commands of all nodes share it.
 *
 * @internal
 */
final class IndexLock implements IndexLockInterface
{
    use LoggerAwareTrait;

    private const RESOURCE_PREFIX = 'generic-data-index:mapping-update:';

    /** The least lifetime on top of the longest copy attempt. */
    private const TTL_MARGIN_SECONDS = 60;

    /** @var array<string, LockInterface> the locks this process holds, by alias name */
    private array $held = [];

    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly int $reindexMaxPolls,
        private readonly int $reindexPollIntervalSeconds,
    ) {
        if (!isset($this->logger)) {
            $this->logger = new NullLogger();
        }
    }

    public function run(string $aliasName, Closure $operation): mixed
    {
        if (isset($this->held[$aliasName])) {
            return $operation();
        }

        $lock = $this->acquire($aliasName);
        $this->held[$aliasName] = $lock;

        try {
            return $operation();
        } finally {
            unset($this->held[$aliasName]);
            $this->release($aliasName, $lock);
        }
    }

    public function refresh(string $aliasName): void
    {
        $lock = $this->held[$aliasName] ?? null;
        if ($lock === null) {
            return;
        }

        try {
            $lock->refresh();
        } catch (LockConflictedException $e) {
            // The lock expired and another process took it over; going on would overlap with its update.
            throw new IndexLockException(
                sprintf('The lock of "%s" was taken over by another mapping update; this one stops', $aliasName),
                0,
                $e
            );
        } catch (LockExceptionInterface $e) {
            // A transient store failure, for example a dropped database connection: retried once.
            $this->refreshAgain($aliasName, $lock);
        }
    }

    /**
     * @throws IndexLockException
     */
    private function refreshAgain(string $aliasName, LockInterface $lock): void
    {
        try {
            $lock->refresh();
        } catch (LockExceptionInterface $e) {
            throw new IndexLockException(
                sprintf(
                    'The lock of "%s" could not be refreshed, the mapping update stops: %s',
                    $aliasName,
                    $e->getPrevious()?->getMessage() ?? $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    /**
     * @throws IndexLockException
     */
    private function acquire(string $aliasName): LockInterface
    {
        $lock = $this->lockFactory->createLock(self::RESOURCE_PREFIX . $aliasName, $this->ttl());

        try {
            if ($lock->acquire()) {
                return $lock;
            }

            $this->logger->notice(sprintf('Waiting for another mapping update of "%s" to finish', $aliasName));
            $lock->acquire(true);
        } catch (LockAcquiringException $e) {
            throw new IndexLockException(
                sprintf('Taking the lock of "%s" failed: %s', $aliasName, $e->getMessage()),
                0,
                $e
            );
        }

        return $lock;
    }

    private function release(string $aliasName, LockInterface $lock): void
    {
        try {
            $lock->release();
        } catch (LockReleasingException $e) {
            // The lock expired during the update and another process took it over; nothing is left to release.
            $this->logger->warning(sprintf(
                'The lock of "%s" was not released cleanly: %s',
                $aliasName,
                $e->getMessage()
            ));
        }
    }

    /**
     * The longest copy attempt plus a quarter of it (at least a minute) for the requests around the copy and the
     * latency of the status polls. An attempt waits for its task at most max_polls × poll_interval, and before and
     * after that it may wait for a cancelled task to stop (a leftover of an aborted attempt, or its own one on a
     * timeout); the lock must outlive all of it, or another update takes it over while the attempt still deletes
     * and switches indices. The handler and the copy service refresh the lock before every further attempt, so an
     * update never outlives its lock, while a lock whose process died expires.
     */
    private function ttl(): float
    {
        $attemptPolls = $this->reindexMaxPolls + 2 * SearchIndexServiceInterface::REINDEX_CANCEL_POLLS;
        $attemptWait = $attemptPolls * $this->reindexPollIntervalSeconds;

        return (float) ($attemptWait + max(self::TTL_MARGIN_SECONDS, intdiv($attemptWait, 4)));
    }
}
