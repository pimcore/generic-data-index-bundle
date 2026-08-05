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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Paging\PaginationInfo;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultAggregation;

final readonly class DataObjectSearchResult
{
    public function __construct(
        /** @var DataObjectSearchResultItem[] */
        private array $items,
        private PaginationInfo $pagination,
        /** @var SearchResultAggregation[] */
        private array $aggregations = [],
        private ?float $maxScore = null,
    ) {
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getPagination(): PaginationInfo
    {
        return $this->pagination;
    }

    /**
     * The highest relevance score across all hits (OpenSearch `hits.max_score`), or null when the
     * query produced no scores. Useful to normalise per-item
     * {@see DataObjectSearchResultItem::getScore()} values for display.
     */
    public function getMaxScore(): ?float
    {
        return $this->maxScore;
    }

    public function getAggregations(): array
    {
        return $this->aggregations;
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

    public function getIds(): array
    {
        return array_map(
            static fn (DataObjectSearchResultItem $item) => $item->getId(),
            $this->items
        );
    }
}
