<?php

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Element;


use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Model\User;

/**
 * @internal
 */
interface ElementSearchHelperInterface
{
    public function addSearchRestrictions(SearchInterface $search): SearchInterface;

    public function performSearch(SearchInterface $search, string $indexName): SearchResult;

    public function hydrateSearchResultHits(SearchResult $searchResult, array $childrenCounts, ?User $user = null): array;
}