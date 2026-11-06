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

use Closure;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexLockInterface;

/**
 * A lock double that reports when a lock is taken and renewed, and lets the test act in between (for example as
 * the process that held the lock before).
 *
 * @internal
 */
final class ObservableIndexLock implements IndexLockInterface
{
    private readonly Closure $onRefresh;

    public function __construct(
        private readonly Closure $onAcquire,
        ?Closure $onRefresh = null,
    ) {
        $this->onRefresh = $onRefresh ?? static fn () => null;
    }

    public function run(string $aliasName, Closure $operation): mixed
    {
        ($this->onAcquire)($aliasName);

        return $operation();
    }

    public function refresh(string $aliasName): void
    {
        ($this->onRefresh)($aliasName);
    }
}
