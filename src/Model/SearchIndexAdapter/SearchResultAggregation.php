<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter;

class SearchResultAggregation
{
    public function __construct(
        private string       $name,
        /** SearchResultAggregationBucket[] */
        private array        $buckets,
        private readonly int $otherDocCount,
        private int          $docCountErrorUpperBound,
    )
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBuckets(): array
    {
        return $this->buckets;
    }

    public function getOtherDocCount(): int
    {
        return $this->otherDocCount;
    }

    public function getDocCountErrorUpperBound(): int
    {
        return $this->docCountErrorUpperBound;
    }


}