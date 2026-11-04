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

/**
 * Serializes the mapping updates of one index across processes: two workers or commands never copy or recreate
 * the same index at the same time.
 *
 * @internal
 */
interface IndexLockInterface
{
    /**
     * Runs a mapping update while holding the lock of its index alias. When another process holds the lock, the
     * update waits until that process has finished. A nested call for an alias whose lock this process already
     * holds runs inside that lock.
     *
     * @template T
     *
     * @param Closure(): T $operation
     *
     * @return T
     *
     * @throws IndexLockException when the lock store fails
     */
    public function run(string $aliasName, Closure $operation): mixed;

    /**
     * Extends the lifetime of the held lock before a further attempt of the update. Does nothing without a held
     * lock.
     */
    public function refresh(string $aliasName): void;
}
