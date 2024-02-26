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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\PaginatedSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Tree\ChildrenCountAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Modifier\SearchModifierServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;

/**
 * @internal
 */
final class SearchHelper implements SearchHelperInterface
{
    public function __construct(
        private readonly SearchIndexServiceInterface $searchIndexService,
        private readonly SearchModifierServiceInterface $searchModifierService
    ) {
    }

    public function performSearch(PaginatedSearchInterface $search, string $indexName): SearchResult
    {
        $adapterSearch = $this->searchIndexService->createPaginatedSearch($search->getPage(), $search->getPageSize());
        $this->searchModifierService->applyModifiersFromSearch($search, $adapterSearch);

        return $this
            ->searchIndexService
            ->search($adapterSearch, $indexName);
    }

    /**
     * @return int[]
     */
    public function getChildrenCounts(
        SearchResult $searchResult,
        string $indexName,
        PaginatedSearchInterface $paginatedSearch
    ): array {
        $parentIds = $searchResult->getIds();

        if (empty($parentIds)) {
            return [];
        }

        $childrenCountAggregation = new ChildrenCountAggregation($parentIds);

        $paginatedSearch->addModifier($childrenCountAggregation);

        $searchResult = $this->performSearch($paginatedSearch, $indexName);

        $childrenCounts = [];
        foreach($parentIds as $parentId) {
            $childrenCounts[$parentId] = 0;
        }

        if ($aggregation = $searchResult->getAggregation($childrenCountAggregation->getAggregationName())) {
            foreach($aggregation->getBuckets() as $bucket) {
                $childrenCounts[$bucket->getKey()] = $bucket->getDocCount();
            }
        }

        return $childrenCounts;
    }
}
