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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Support;

use OutOfBoundsException;
use Pimcore\Contracts\Diagnostics\CheckInterface;

/**
 * Test-only holder of the services tagged pimcore.diagnostics.check, so the functional tests reach the private
 * check services the way a diagnostics consumer does. Empty on a core without the diagnostics contract.
 *
 * @internal
 */
final class DiagnosticChecks
{
    /** @var array<string, CheckInterface> */
    private array $checks = [];

    /**
     * @param iterable<CheckInterface> $checks
     */
    public function __construct(iterable $checks)
    {
        foreach ($checks as $check) {
            $this->checks[$check->getId()] = $check;
        }
    }

    public function get(string $id): CheckInterface
    {
        return $this->checks[$id] ?? throw new OutOfBoundsException('No diagnostic check with id ' . $id);
    }

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        return array_keys($this->checks);
    }
}
