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
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Psr\Log\NullLogger;
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

    /** Lifetime on top of the longest wait for one copy task, for the requests around the copy. */
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
        } catch (LockConflictedException|LockAcquiringException $e) {
            // The lock expired and another process took it over, or the store is unreachable. This update goes
            // on: the search service keeps the live index safe when two copies overlap.
            $this->logger->warning(sprintf(
                'The lock of "%s" could not be refreshed, another mapping update of the index may start before '
                . 'this one has finished: %s',
                $aliasName,
                $e->getMessage()
            ));
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
     * The longest wait for one copy task plus a margin. The handler refreshes the lock before every further
     * attempt, so a copy that waits for the engine never outlives its lock, while a lock whose process died
     * expires.
     */
    private function ttl(): float
    {
        return (float) ($this->reindexMaxPolls * $this->reindexPollIntervalSeconds + self::TTL_MARGIN_SECONDS);
    }
}
