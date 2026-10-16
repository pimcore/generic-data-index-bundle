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
 * Indices that were queued for a rebuild from the database because applying the mapping left
 * fields out of the copy or created the index empty.
 *
 * @internal
 */
final readonly class IndexRebuildReport
{
    /**
     * @param array<string, MappingUpdateResult> $rebuilds keyed by index label (class name, "assets", "documents")
     */
    public function __construct(private array $rebuilds = [])
    {
    }

    /**
     * @return array<string, MappingUpdateResult>
     */
    public function getRebuilds(): array
    {
        return $this->rebuilds;
    }

    public function isEmpty(): bool
    {
        return $this->rebuilds === [];
    }
}
