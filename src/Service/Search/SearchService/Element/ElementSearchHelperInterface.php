<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Element;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\DefaultSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Model\User;

/**
 * @internal
 */
interface ElementSearchHelperInterface
{
    public function addSearchRestrictions(
        SearchInterface $search,
        PermissionTypes $permissionType = PermissionTypes::LIST
    ): SearchInterface;

    public function performSearch(SearchInterface $search, string $indexName): SearchResult;

    public function createAdapterSearch(
        SearchInterface $search,
        string $indexName,
        bool $enableOrderByPageNumber = false
    ): DefaultSearchInterface;

    public function hydrateSearchResultHits(
        SearchResult $searchResult,
        array $childrenCounts,
        ?User $user = null
    ): array;
}
