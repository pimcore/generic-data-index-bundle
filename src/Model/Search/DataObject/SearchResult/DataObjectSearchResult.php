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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Paging\PaginationInfo;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultAggregation;

final class DataObjectSearchResult
{
    public function __construct(
        /** @var DataObjectSearchResultItem[] */
        private readonly array $items,
        private readonly PaginationInfo $pagination,
        /** @var SearchResultAggregation[] */
        private readonly array $aggregations = [],
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
            static fn(DataObjectSearchResultItem $item) => $item->getId(),
            $this->items
        );
    }
}
