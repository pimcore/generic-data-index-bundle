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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch;

use function array_key_exists;

/**
 * @internal
 */
final class LockedElementsCache implements LockedElementsCacheInterface
{
    /** @var array<string, array<string, string>|null> */
    private array $locksByIndex = [];

    public function isLoaded(string $indexName): bool
    {
        return array_key_exists($indexName, $this->locksByIndex);
    }

    public function getLocks(string $indexName): ?array
    {
        return $this->locksByIndex[$indexName] ?? null;
    }

    public function setLocks(string $indexName, ?array $locks): void
    {
        $this->locksByIndex[$indexName] = $locks;
    }

    public function reset(): void
    {
        $this->locksByIndex = [];
    }
}
