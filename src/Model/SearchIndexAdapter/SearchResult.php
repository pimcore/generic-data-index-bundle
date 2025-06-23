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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;

final readonly class SearchResult
{
    public function __construct(
        /** SearchResultHit[] */
        private array $hits,
        /** SearchResultAggregation[] */
        private array $aggregations,
        private int $totalHits,
        private ?float $maxScore,
        private AdapterSearchInterface $search,
        private array $response,
    ) {
    }

    /**
     * @return SearchResultHit[]
     */
    public function getHits(): array
    {
        return $this->hits;
    }

    public function getLastHit(): ?SearchResultHit
    {
        return $this->hits[array_key_last($this->hits)] ?? null;
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

    public function getResponse(): array
    {
        return $this->response;
    }
}
