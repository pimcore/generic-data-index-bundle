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

use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Throwable;

/**
 * An in-memory lock store that counts its calls and can act like a store whose lock another process holds, a
 * store that fails, or a store whose key outlives the release.
 */
final class RecordingLockStore implements PersistingStoreInterface
{
    private readonly InMemoryStore $inner;

    private int $conflictingSaves = 0;

    private int $conflictingRefreshes = 0;

    private int $failingRefreshes = 0;

    private ?Throwable $refreshFailure = null;

    private ?Throwable $saveFailure = null;

    private bool $keepKeysAfterDelete = false;

    private int $saves = 0;

    private int $deletes = 0;

    private int $refreshes = 0;

    /** @var array<string, int> refreshes by lock resource */
    private array $refreshesByResource = [];

    private ?string $conflictingResource = null;

    private ?float $lastTtl = null;

    /** @var array<string, true> */
    private array $heldResources = [];

    public function __construct()
    {
        $this->inner = new InMemoryStore();
    }

    /** The next saves fail as if another process held the lock. */
    public function conflictOnNextSaves(int $count): void
    {
        $this->conflictingSaves = $count;
    }

    /** The next refreshes fail as if another process had taken the lock over. */
    /** The next refresh of this one resource is answered with a conflict: another process took the lock over. */
    public function conflictOnNextRefreshOf(string $resource): void
    {
        $this->conflictingResource = $resource;
    }

    public function conflictOnNextRefreshes(int $count): void
    {
        $this->conflictingRefreshes = $count;
    }

    /** The next refreshes fail with a store error. */
    public function failNextRefreshesWith(Throwable $failure, int $count = 1): void
    {
        $this->refreshFailure = $failure;
        $this->failingRefreshes = $count;
    }

    public function failSavesWith(Throwable $failure): void
    {
        $this->saveFailure = $failure;
    }

    /** The key stays in the store after a delete, as when the lock expired and another process took it. */
    public function keepKeysAfterDelete(): void
    {
        $this->keepKeysAfterDelete = true;
    }

    public function save(Key $key): void
    {
        ++$this->saves;
        if ($this->conflictingSaves > 0) {
            --$this->conflictingSaves;

            throw new LockConflictedException('held by another process');
        }
        if ($this->saveFailure !== null) {
            throw $this->saveFailure;
        }
        $this->inner->save($key);
        $this->heldResources[(string) $key] = true;
    }

    public function delete(Key $key): void
    {
        ++$this->deletes;
        $this->inner->delete($key);
        unset($this->heldResources[(string) $key]);
    }

    public function exists(Key $key): bool
    {
        return $this->keepKeysAfterDelete || $this->inner->exists($key);
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
        ++$this->refreshes;
        $this->refreshesByResource[(string) $key] = ($this->refreshesByResource[(string) $key] ?? 0) + 1;
        $this->lastTtl = $ttl;
        if ($this->conflictingResource === (string) $key) {
            $this->conflictingResource = null;

            throw new LockConflictedException('taken over by another process');
        }
        if ($this->conflictingRefreshes > 0) {
            --$this->conflictingRefreshes;

            throw new LockConflictedException('taken over by another process');
        }
        if ($this->failingRefreshes > 0 && $this->refreshFailure !== null) {
            --$this->failingRefreshes;

            throw $this->refreshFailure;
        }
        $this->inner->putOffExpiration($key, $ttl);
    }

    public function getSaves(): int
    {
        return $this->saves;
    }

    public function getDeletes(): int
    {
        return $this->deletes;
    }

    public function getRefreshesOf(string $resource): int
    {
        return $this->refreshesByResource[$resource] ?? 0;
    }

    public function getRefreshes(): int
    {
        return $this->refreshes;
    }

    public function getLastTtl(): ?float
    {
        return $this->lastTtl;
    }

    /** @return string[] the resources currently locked */
    public function getHeldResources(): array
    {
        return array_keys($this->heldResources);
    }
}
