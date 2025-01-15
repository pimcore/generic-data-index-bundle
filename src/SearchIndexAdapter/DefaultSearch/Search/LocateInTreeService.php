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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\LocateInTreeServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Modifier\SearchModifierServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\IndexNameResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final readonly class LocateInTreeService implements LocateInTreeServiceInterface
{
    public function __construct(
        private SearchModifierServiceInterface $searchModifierService,
        private SearchIndexServiceInterface $searchIndexService,
        private SearchIndexConfigServiceInterface $searchIndexConfigService,
        private IndexNameResolverInterface $indexNameResolver,
    ) {
    }

    public function getPageNumber(SearchInterface $search, int $elementId): ?int
    {
        $elementPosition = $this->getElementPosition($search, $elementId);

        if ($elementPosition === null) {
            return null;
        }

        return (int) ceil($elementPosition / $search->getPageSize());
    }

    private function getElementPosition(SearchInterface $search, int $elementId): ?int
    {
        $indexName = $this->indexNameResolver->resolveIndexName($search);

        $defaultSearch = $this->createDefaultSearch($search);

        if ($defaultSearch->getSortList()->isEmpty()) {
            throw new InvalidArgumentException('Search must have a sort defined to be able to locate in tree');
        }

        $count = $this->searchIndexService->getCount($defaultSearch, $indexName);

        if ($count < 1) {
            return null;
        }

        $pageSize = $this->calculatePageSize($count);
        $totalPages = (int) ceil($count / $pageSize);

        $lastSearchResult = null;
        for ($page = 1; $page <= $totalPages; $page++) {
            $defaultSearch = $this->createDefaultSearch($search, $pageSize, $lastSearchResult);

            $lastSearchResult = $this->searchIndexService->search(
                $defaultSearch,
                $indexName,
            );

            $position = $this->getElementPositionInSearchResult($lastSearchResult, $elementId);

            if ($position !== null) {
                return ($page - 1) * $pageSize + $position;
            }
        }

        return null;
    }

    private function createDefaultSearch(
        SearchInterface $search,
        ?int $pageSize = null,
        ?SearchResult $searchAfterSearchResult = null
    ): Search {
        $pageSize = $pageSize ?? $search->getPageSize();

        /** @var Search $defaultSearch */
        $defaultSearch = $this->searchIndexService->createPaginatedSearch(1, $pageSize);
        $this->searchModifierService->applyModifiersFromSearch($search, $defaultSearch);

        if ($searchAfterSearchResult !== null) {
            $searchAfter = $searchAfterSearchResult->getLastHit()?->getSort();
            if (empty($searchAfter)) {
                return $defaultSearch;
            }
            $defaultSearch->setSearchAfter(
                $searchAfter
            );
        }

        return $defaultSearch;
    }

    private function getElementPositionInSearchResult(SearchResult $searchResult, int $elementId): ?int
    {
        foreach ($searchResult->getHits() as $index => $hit) {
            if ($hit->getId() === (string) $elementId) {
                return $index + 1;
            }
        }

        return null;
    }

    /**
     * Calculates a reasonable page size based on the amount of elements
     */
    private function calculatePageSize(int $count): int
    {
        $maxResultWindow = $this->searchIndexConfigService->getIndexSettings()['max_result_window'];
        if ($maxResultWindow > 10000) {
            $maxResultWindow = 10000;
        }
        if ($count > 4*$maxResultWindow) {
            return $maxResultWindow;
        }
        if ($count < 2000) {
            return min(2000, $maxResultWindow);
        }

        return (int) ceil($count / 4);
    }
}
