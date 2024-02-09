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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Stats;

final class IndexStatsIndex
{
    public function __construct(
        private readonly string $indexName,
        private readonly int $itemsCount,
        private readonly float $sizeInKb
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