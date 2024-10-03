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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\ElementSearchResultItemInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultHit;
use Pimcore\Model\User;

/**
 * @internal
 */
interface SearchHelperInterface
{
    public function addSearchRestrictions(
        SearchInterface $search,
        string $userPermission,
        string $workspaceType,
        PermissionTypes $permissionType
    ): SearchInterface;

    public function performSearch(SearchInterface $search, string $indexName): SearchResult;

    /**
     * @return int[]
     */
    public function getChildrenCounts(
        SearchResult $searchResult,
        string $indexName,
        SearchInterface $search
    ): array;

    public function hydrateSearchResultHits(
        SearchResult $searchResult,
        array $childrenCounts,
        ?User $user = null
    ): array;

    public function hydrateSearchResultHit(
        SearchResultHit $searchResultHit,
        array $childrenCounts,
        ?User $user = null
    ): ElementSearchResultItemInterface;
}
