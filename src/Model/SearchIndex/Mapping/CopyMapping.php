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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Mapping;

/**
 * The mapping the copy target is created with: the new mapping plus the live mapping of the fields
 * the new mapping no longer has, so their values keep the type they were indexed with.
 *
 * @internal
 */
final readonly class CopyMapping
{
    /**
     * @param array<string, mixed> $properties mapping properties for the copy target
     * @param string[] $carriedPaths removed fields taken over from the live mapping (dotted paths)
     */
    public function __construct(
        private array $properties,
        private array $carriedPaths = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return string[]
     */
    public function getCarriedPaths(): array
    {
        return $this->carriedPaths;
    }
}
