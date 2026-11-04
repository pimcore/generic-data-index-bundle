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

use Symfony\Contracts\Service\ResetInterface;

/**
 * Locked elements per search index, loaded once and reused for the lock checks of one request.
 *
 * @internal
 */
interface LockedElementsCacheInterface extends ResetInterface
{
    public function isLoaded(string $indexName): bool;

    /**
     * @return array<string, string>|null lock value by full path, null if the index has too many
     *                                    locked elements to keep them in memory
     */
    public function getLocks(string $indexName): ?array;

    /**
     * @param array<string, string>|null $locks lock value by full path, null for "too many to cache"
     */
    public function setLocks(string $indexName, ?array $locks): void;
}
