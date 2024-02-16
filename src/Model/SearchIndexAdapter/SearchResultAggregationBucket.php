<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter;

class SearchResultAggregationBucket
{
    public function __construct(
        private readonly string $key,
        private readonly int    $docCount,
    )
    {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getDocCount(): int
    {
        return $this->docCount;
    }

}