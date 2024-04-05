<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter;

/**
 * @internal
 */
final readonly class Mapping
{
    public function __construct(
        private string $mappingName,
        private array $mapping,
    ) {
    }

    public function getMappingName(): string
    {
        return $this->mappingName;
    }

    public function getMapping(): array
    {
        return $this->mapping;
    }
}
