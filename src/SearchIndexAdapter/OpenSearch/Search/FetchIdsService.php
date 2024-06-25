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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\OpenSearchSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort\FieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort\FieldSortList;
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

    private function validateSearch(AdapterSearchInterface $search): OpenSearchSearchInterface
    {
        if (!$search instanceof OpenSearchSearchInterface) {
            throw new InvalidArgumentException('Search must be an instance of OpenSearchSearchInterface');
        }

        return $search;
    }
}
