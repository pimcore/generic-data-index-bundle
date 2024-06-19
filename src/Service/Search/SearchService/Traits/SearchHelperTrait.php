<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Traits;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
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
        $adapterSearch = $this->createAdapterSearch($search, $indexName, true);

        return $this
            ->searchIndexService
            ->search($adapterSearch, $indexName);
    }

    public function createAdapterSearch(
        SearchInterface $search,
        string $indexName,
        bool $enableOrderByPageNumber = false
    ): AdapterSearchInterface
    {
        $adapterSearch = $this->searchIndexService->createPaginatedSearch(
            $search->getPage(),
            $search->getPageSize(),
            $search->isAggregationsOnly()
        );

        if ($enableOrderByPageNumber) {
            $search->addModifier(new OrderByPageNumber($indexName, $search));
        }

        $this->searchModifierService->applyModifiersFromSearch($search, $adapterSearch);

        return $adapterSearch;
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
