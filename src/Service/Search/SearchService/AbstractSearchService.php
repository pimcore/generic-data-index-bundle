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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\ValidationFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\PaginatedSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Tree\ChildrenCountAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Modifier\SearchModifierServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Pagination\PaginationInfoServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
abstract class AbstractSearchService implements SearchServiceInterface
{

    protected PaginationInfoServiceInterface $paginationInfoService;

    protected SearchIndexServiceInterface $searchIndexService;

    protected SearchModifierServiceInterface $searchModifierService;

    /**
     * @throws ValidationFailedException
     */
    protected function performSearch(PaginatedSearchInterface $search, string $indexName): SearchResult
    {
        $adapterSearch = $this->searchIndexService->createPaginatedSearch($search->getPage(), $search->getPageSize());
        $this->applyModifiersFromSearch($search, $adapterSearch);

        return $this
            ->searchIndexService
            ->search($adapterSearch, $indexName);
    }

    /**
     * @return int[]
     */
    protected function getChildrenCounts(
        SearchResult $searchResult,
        string $indexName
    ): array {
        $parentIds = $searchResult->getIds();

        if (empty($parentIds)) {
            return [];
        }

        $childrenCountAggregation = new ChildrenCountAggregation($parentIds);

        $search = (new AssetSearch())
            ->addModifier($childrenCountAggregation);

        $searchResult = $this->performSearch($search, $indexName);

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

    protected function applyModifiersFromSearch(
        SearchInterface $search,
        AdapterSearchInterface $adapterSearch
    ): self {
        $this->searchModifierService->applyModifiersFromSearch(
            $this,
            $search,
            $adapterSearch
        );

        return $this;
    }

    #[Required]
    public function setServices(
        PaginationInfoServiceInterface $paginationInfoService,
        SearchIndexServiceInterface $searchIndexService,
        SearchModifierServiceInterface $searchModifierService,
    ): AbstractSearchService {
        $this->paginationInfoService = $paginationInfoService;
        $this->searchIndexService = $searchIndexService;
        $this->searchModifierService = $searchModifierService;

        return $this;
    }
}
