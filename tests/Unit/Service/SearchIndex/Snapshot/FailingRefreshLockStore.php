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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Snapshot;

use RuntimeException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * An in-memory lock store whose next refreshes, once armed, fail the way the DBAL store does
 * after the server closed its idle connection ("MySQL server has gone away").
 */
final class FailingRefreshLockStore implements PersistingStoreInterface
{
    private readonly InMemoryStore $inner;

    private int $failingRefreshes = 0;

    private int $refreshCalls = 0;

    public function __construct()
    {
        $this->inner = new InMemoryStore();
    }

    /**
     * Arms the failures only after the lock is held: acquire() refreshes the lock itself.
     */
    public function failNextRefreshes(int $count): void
    {
        $this->failingRefreshes = $count;
        $this->refreshCalls = 0;
    }

    public function save(Key $key): void
    {
        $this->inner->save($key);
    }

    public function delete(Key $key): void
    {
        $this->inner->delete($key);
    }

    public function exists(Key $key): bool
    {
        return $this->inner->exists($key);
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
        ++$this->refreshCalls;
        if ($this->failingRefreshes > 0) {
            --$this->failingRefreshes;

            throw new RuntimeException('General error: 2006 MySQL server has gone away');
        }
        $this->inner->putOffExpiration($key, $ttl);
    }

    public function getRefreshCalls(): int
    {
        return $this->refreshCalls;
    }
}
