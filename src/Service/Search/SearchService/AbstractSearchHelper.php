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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Tree\ChildrenCountAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Workspaces\WorkspaceQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\OrderByPageNumber;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultHit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Modifier\SearchModifierServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\UserPermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Traits\SearchHelperTrait;
use Pimcore\Model\User;

/**
 * @internal
 */
abstract class AbstractSearchHelper implements SearchHelperInterface
{
    use SearchHelperTrait;

    public function __construct(
        private readonly SearchIndexServiceInterface $searchIndexService,
        private readonly SearchModifierServiceInterface $searchModifierService,
        private readonly UserPermissionServiceInterface $userPermissionService,
    ) {
    }

    public function addSearchRestrictions(
        SearchInterface $search,
        string $userPermission,
        string $workspaceType
    ): SearchInterface {
        $user = $search->getUser();
        if (!$user) {
            return $search;
        }

        $this->userPermissionService->canSearch($user, $userPermission);
        if (!$user->isAdmin()) {
            $search->addModifier(new WorkspaceQuery(
                $workspaceType,
                $user,
                PermissionTypes::LIST->value
            ));
        }

        return $search;
    }

    /**
     * @return int[]
     */
    public function getChildrenCounts(
        SearchResult $searchResult,
        string $indexName,
        SearchInterface $search
    ): array {
        $parentIds = $searchResult->getIds();

        if (empty($parentIds)) {
            return [];
        }

        $childrenCountAggregation = new ChildrenCountAggregation($parentIds);

        $search->addModifier($childrenCountAggregation);

        $searchResult = $this->performSearch($search, $indexName);

        $childrenCounts = [];
        foreach ($parentIds as $parentId) {
            $childrenCounts[$parentId] = 0;
        }

        if ($aggregation = $searchResult->getAggregation($childrenCountAggregation->getAggregationName())) {
            foreach ($aggregation->getBuckets() as $bucket) {
                $childrenCounts[$bucket->getKey()] = $bucket->getDocCount();
            }
        }

        return $childrenCounts;
    }

    abstract public function hydrateSearchResultHit(
        SearchResultHit $searchResultHit,
        array $childrenCounts,
        ?User $user = null
    ): ElementSearchResultItemInterface;
}
