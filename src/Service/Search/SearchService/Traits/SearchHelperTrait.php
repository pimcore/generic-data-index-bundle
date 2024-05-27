<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Traits;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\OrderByPageNumber;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Model\User;

/**
 * @internal
 */
trait SearchHelperTrait
{
    public function performSearch(SearchInterface $search, string $indexName): SearchResult
    {
        $adapterSearch = $this->searchIndexService->createPaginatedSearch(
            $search->getPage(),
            $search->getPageSize(),
            $search->isAggregationsOnly()
        );

        $search->addModifier(new OrderByPageNumber($indexName, $search));
        $this->searchModifierService->applyModifiersFromSearch($search, $adapterSearch);

        return $this
            ->searchIndexService
            ->search($adapterSearch, $indexName);
    }

    public function hydrateSearchResultHits(
        SearchResult $searchResult,
        array $childrenCounts,
        ?User $user = null
    ): array {
        $results = [];

        foreach ($searchResult->getHits() as $hit) {
            $results[] = $this->hydrateSearchResultHit($hit, $childrenCounts, $user);
        }

        return $results;
    }
}