<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Stats;

final class IndexStats
{
    public function __construct(
        private readonly int $countIndexQueueEntries,
        /** @var IndexStatsIndex[] */
        private readonly array $indices
    )
    {
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