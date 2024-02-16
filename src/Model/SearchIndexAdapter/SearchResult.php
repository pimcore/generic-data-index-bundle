<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter;

final class SearchResult
{
    public function __construct(
        /** SearchResultHit[] */
        private array          $hits,
        /** SearchResultAggregation[] */
        private readonly array $aggregations,
        private int            $totalHits,
        private float          $maxScore,
    )
    {
    }

    /**
     * @return SearchResultHit[]
     */
    public function getHits(): array
    {
        return $this->hits;
    }

    /**
     * @return SearchResultAggregation[]
     */
    public function getAggregations(): array
    {
        return $this->aggregations;
    }

    public function getTotalHits(): int
    {
        return $this->totalHits;
    }

    public function getMaxScore(): float
    {
        return $this->maxScore;
    }


}