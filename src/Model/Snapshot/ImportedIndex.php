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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot;

/**
 * @internal
 */
final readonly class ImportedIndex
{
    public function __construct(
        public string $shortName,
        public string $aliasName,
        public int $expectedCount,
        public int $actualCount,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->expectedCount === $this->actualCount;
    }
}
