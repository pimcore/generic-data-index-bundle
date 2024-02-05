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

final class IndexStats
{
    public function __construct(
        private readonly int $countIndexQueueEntries,
        /** @var IndexStatsIndex[] */
        private readonly array $indices
    ) {
    }

    public function getCountIndexQueueEntries(): int
    {
        return $this->countIndexQueueEntries;
    }

    /** @return IndexStatsIndex[] */
    public function getIndices(): array
    {
        return $this->indices;
    }
}
