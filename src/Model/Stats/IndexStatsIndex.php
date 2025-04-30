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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Stats;

final readonly class IndexStatsIndex
{
    public function __construct(
        private string $indexName,
        private int $itemsCount,
        private float $sizeInKb
    ) {
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function getItemsCount(): int
    {
        return $this->itemsCount;
    }

    public function getSizeInKb(): float
    {
        return $this->sizeInKb;
    }
}
