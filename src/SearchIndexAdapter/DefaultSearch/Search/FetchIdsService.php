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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\DefaultSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSortList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\FetchIdsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;

/**
 * @internal
 */
final readonly class FetchIdsService implements FetchIdsServiceInterface
{
    public function __construct(
        private FetchIdsBySearchServiceInterface $fetchIdsBySearchService,
        private SearchIndexServiceInterface $searchIndexService,
    ) {
    }

    public function fetchIdsForCurrentPage(AdapterSearchInterface $search, string $indexName): array
    {
        $search = $this->validateSearch($search);
        $search = clone $search;
        $search->setSource(false);

        return $this->searchIndexService
            ->search($search, $indexName)
            ->getIds()
        ;
    }

    public function fetchAllIds(AdapterSearchInterface $search, string $indexName): array
    {
        $search = $this->validateSearch($search);

        // if sort is not defined sort by id to be able to fetch all ids using search after
        if ($search->getSortList()->isEmpty()) {
            $search->setSortList(new FieldSortList([new FieldSort(SystemField::ID->getPath())]));
        }

        return $this->fetchIdsBySearchService->fetchAllIds($search, $indexName, false);
    }

    private function validateSearch(AdapterSearchInterface $search): DefaultSearchInterface
    {
        if (!$search instanceof DefaultSearchInterface) {
            throw new InvalidArgumentException('Search must be an instance of OpenSearchSearchInterface');
        }

        return $search;
    }
}
