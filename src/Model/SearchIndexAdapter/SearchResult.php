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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;

final class SearchResult
{
    public function __construct(
        /** SearchResultHit[] */
        private readonly array $hits,
        /** SearchResultAggregation[] */
        private readonly array $aggregations,
        private readonly int $totalHits,
        private readonly ?float $maxScore,
        private readonly AdapterSearchInterface $search,
    ) {
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

    public function getMaxScore(): ?float
    {
        return $this->maxScore;
    }

    /**
     * @return int[]
     */
    public function getIds(): array
    {
        $result = [];
        foreach ($this->getHits() as $hit) {
            $result[] = (int) $hit->getId();
        }

        return $result;
    }

    public function getAggregation(string $aggregationName): ?SearchResultAggregation
    {
        foreach ($this->aggregations as $aggregation) {
            if ($aggregation->getName() === $aggregationName) {
                return $aggregation;
            }
        }

        return null;
    }

    public function getSearch(): AdapterSearchInterface
    {
        return $this->search;
    }
}
