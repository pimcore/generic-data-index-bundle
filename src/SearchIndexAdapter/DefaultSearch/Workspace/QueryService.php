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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Aggregation\Aggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\AssetWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DataObjectWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DocumentWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\WorkspaceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Workspace\QueryServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\PathServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workspace\WorkspaceServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\User;

/**
 * @internal
 */
final class QueryService implements QueryServiceInterface
{
    use LoggerAwareTrait;

    private const ALLOWED_PATHS_KEY = 'allowedPaths';

    private const DECLINED_PATHS_KEY = 'declinedPaths';

    public function __construct(
        private readonly PermissionServiceInterface $permissionService,
        private readonly WorkspaceServiceInterface $workspaceService,
        private readonly SearchIndexServiceInterface $searchIndexService,
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        private readonly PathServiceInterface $pathService,
    ) {
    }

    public function getWorkspaceQuery(string $workspaceType, ?User $user, string $permission): BoolQuery
    {
        $workspacesQuery = new BoolQuery();
        if ($user?->isAdmin()) {
            return $workspacesQuery;
        }

        $workspacesQuery->addCondition(
            ConditionType::MUST->value,
            ['bool' => $this->getWorkspaceGroupsQuery(
                $workspaceType,
                $user,
                $permission
            )->toArray()]
        );

        return $workspacesQuery;
    }

    private function getWorkspaceGroupsQuery(string $workspaceType, ?User $user, string $permission): BoolQuery
    {
        $group = $this->getGroupedWorkspaces($workspaceType, $user);

        if (empty($group)) {
            return $this->createNoWorkspaceAllowedQuery();
        }

        return $this->createWorkspacesGroupQuery(
            $workspaceType,
            $this->getCategorizedWorkspacePaths($group, $permission)
        );
    }

    private function getGroupedWorkspaces(string $workspaceType, ?User $user): array
    {
        $groupedWorkspaces = [];
        if (!$user) {
            return $groupedWorkspaces;
        }

        $userWorkspaces = $this->workspaceService->getUserWorkspaces(
            $workspaceType,
            $user
        );

        /** @var WorkspaceInterface $userWorkspace */
        foreach ($userWorkspaces as $userWorkspace) {
            $groupedWorkspaces[$userWorkspace->getPath()] = $userWorkspace;
        }

        foreach ($user->getRoles() as $roleId) {
            $roleWorkspaces = $this->workspaceService->getRoleWorkspaces($workspaceType, $roleId);
            /** @var WorkspaceInterface $roleWorkspace */
            foreach ($roleWorkspaces as $roleWorkspace) {
                if (!isset($groupedWorkspaces[$roleWorkspace->getPath()])) {
                    $groupedWorkspaces[$roleWorkspace->getPath()] = $roleWorkspace;
                }
            }
        }

        return $groupedWorkspaces;
    }

    private function getCategorizedWorkspacePaths(array $group, string $permission): array
    {
        $allowedPaths = [];
        $declinedPaths = [];

        /** @var WorkspaceInterface $workspace */
        foreach ($group as $workspace) {

            if ($this->permissionService->checkWorkspacePermission($workspace, $permission)) {
                $allowedPaths[] = $workspace->getPath();
            } else {
                $declinedPaths[] = $workspace->getPath();
            }
        }

        $allowedPaths = array_unique($allowedPaths);
        $declinedPaths = array_unique($declinedPaths);
        $declinedPaths = $this->keepDeclinedPathsWithinAllowed($declinedPaths, $allowedPaths);

        return [
            self::ALLOWED_PATHS_KEY => $allowedPaths,
            self::DECLINED_PATHS_KEY => $declinedPaths,
        ];
    }

    private function createWorkspacesGroupQuery(string $workspaceType, array $categorizedPaths): BoolQuery
    {
        $allowedPaths = $categorizedPaths[self::ALLOWED_PATHS_KEY];
        $originalDeclinedPaths = $categorizedPaths[self::DECLINED_PATHS_KEY];
        $declinedPaths = $this->evaluateDeclinedPaths($workspaceType, $allowedPaths, $originalDeclinedPaths);

        if (empty($allowedPaths)) {
            return $this->createNoWorkspaceAllowedQuery();
        }

        $excludedPaths = $this->evaluateExcludedPaths($allowedPaths, $declinedPaths);
        $excludedFullPaths = $this->evaluateExcludedFullPaths($allowedPaths, $declinedPaths);

        $query = new BoolQuery();

        $this->addQueryByMainPath($query, $allowedPaths);
        $allowedMainPaths = $this->pathService->removeSubPaths($allowedPaths);

        if (count($excludedFullPaths) > 0) {
            $this->addQueryToExcludeFullPaths($query, $excludedFullPaths);
        }

        $additionalIncludedPaths = [];
        if (count($excludedPaths) > 0) {
            $this->addQueryForExcludedPaths($query, $excludedPaths);

            /* we need to explicitly include all allowed sub paths
               as all direct children are excluded by the condition above */
            $additionalIncludedPaths = array_merge(
                $additionalIncludedPaths,
                array_values(array_diff($allowedPaths, $allowedMainPaths))
            );
        }

        /* we need to include all parent paths of the allowed paths
           as otherwise it will not be possible to navigate to the allowed paths in the tree */
        $additionalIncludedPaths = array_merge(
            $additionalIncludedPaths,
            $this->pathService->getAllParentPaths($allowedMainPaths),
            $this->getAllDeclinedParentPaths($originalDeclinedPaths, $allowedPaths)
        );
        $additionalIncludedPaths = array_unique($additionalIncludedPaths);

        if (count($additionalIncludedPaths)) {
            return $this->addQueryForAdditionalIncludedPaths($query, $additionalIncludedPaths);
        }

        return $query;
    }

    private function keepDeclinedPathsWithinAllowed(array $declinedPaths, array $allowedPaths): array
    {
        foreach ($declinedPaths as $index => $declinedPath) {
            $isIncluded = false;
            foreach ($allowedPaths as $allowedPath) {
                if ($declinedPath === $allowedPath || $this->pathService->isSubPath($declinedPath, $allowedPath)) {
                    $isIncluded = true;
                }
            }
            if (!$isIncluded) {
                unset($declinedPaths[$index]);
            }
        }

        return $declinedPaths;
    }

    private function addQueryByMainPath(BoolQuery $query, array $allowedPaths): void
    {
        $allowedMainPaths = $this->pathService->removeSubPaths($allowedPaths);

        if (count($allowedMainPaths) === 1 && $allowedMainPaths[0] === '/') {
            $query->addCondition(
                ConditionType::SHOULD->value,
                [
                    'exists' => [
                        'field' => SystemField::FULL_PATH->getPath(),
                    ],
                ]
            );

            return;
        }

        $query->addCondition(
            ConditionType::SHOULD->value,
            [
                'terms' => [
                    SystemField::FULL_PATH->getPath() => $allowedMainPaths,
                ],
            ]
        );
    }

    private function addQueryToExcludeFullPaths(BoolQuery $query, array $excludedFullPaths): void
    {
        $query->addCondition(
            ConditionType::MUST_NOT->value,
            [
                'terms' => [
                    SystemField::FULL_PATH->getPath() => $this->pathService->removeSubPaths($excludedFullPaths),
                ],
            ]
        );
    }

    private function addQueryForExcludedPaths(BoolQuery $query, array $excludedPaths): void
    {
        $query->addCondition(
            ConditionType::MUST_NOT->value,
            [
                'terms' => [
                    SystemField::PATH->getPath('keyword')
                    => $this->pathService->appendSlashes($excludedPaths),
                ],
            ]
        );

        $query->addCondition(
            ConditionType::MUST_NOT->value,
            [
                'terms' => [
                    SystemField::FULL_PATH->getPath('keyword') => $excludedPaths,
                ],
            ]
        );
    }

    private function addQueryForAdditionalIncludedPaths(BoolQuery $query, array $additionalIncludedPaths): BoolQuery
    {
        return new BoolQuery([
            ConditionType::SHOULD->value => [
                $query,
                [
                    'terms' => [
                        SystemField::FULL_PATH->getPath('keyword') => $additionalIncludedPaths,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Handles excluded paths where allowed sub paths exist
     * see https://github.com/pimcore/generic-data-index-bundle/issues/73
     */
    private function evaluateDeclinedPaths(string $workspaceType, array $allowedPaths, array $declinedPaths): array
    {
        $indexName = $this->getIndexName($workspaceType);
        if ($indexName === null) {
            return $declinedPaths;
        }

        $boolQuery = new BoolQuery();
        foreach ($declinedPaths as $declinedPath) {
            $allowedSubPaths = $this->pathService->getContainedSubPaths($declinedPath, $allowedPaths);
            if (count($allowedSubPaths) > 0) {
                $subQuery = new BoolQuery([
                    ConditionType::FILTER->value => [
                        [
                            'term' => [
                                SystemField::PATH->getPath() => $declinedPath,
                            ],
                        ],
                        [
                            'range' => [
                                SystemField::PATH_LEVEL->getPath() => [
                                    'lte' => $this->pathService->calculateLongestPathLevel($allowedSubPaths),
                                ],
                            ],
                        ],
                    ],
                    ConditionType::MUST_NOT->value => [
                        'terms' => [
                            SystemField::FULL_PATH->getPath() => $allowedSubPaths,
                        ],
                    ],
                ]);

                $boolQuery->addCondition(ConditionType::SHOULD->value, $subQuery->toArray(true));
            }
        }

        if (!$boolQuery->isEmpty()) {
            $search = (new Search())
                ->setSize(0)
                ->addQuery($boolQuery)
                ->addAggregation(new Aggregation('paths', [
                    'terms' => [
                        'field' => SystemField::PATH->getPath('keyword'),
                        'size' => 10000,
                    ],
                ]));

            $result = $this->searchIndexService->search($search, $indexName);
            $buckets = $result->getAggregation('paths')?->getBuckets() ?? [];
            foreach ($buckets as $bucket) {
                if ($bucket->getKey() === '/') {
                    continue;
                }
                $declinedPaths[] = rtrim($bucket->getKey(), '/');
            }
        }

        return array_unique($declinedPaths);
    }

    private function getIndexName(string $workspaceType): ?string
    {
        $mapping = [
            AssetWorkspace::WORKSPACE_TYPE => IndexName::ASSET->value,
            DataObjectWorkspace::WORKSPACE_TYPE => IndexName::DATA_OBJECT->value,
            DocumentWorkspace::WORKSPACE_TYPE => IndexName::DOCUMENT->value,
        ];

        $indexName = $mapping[$workspaceType] ?? null;

        if ($indexName === null) {
            $this->logger->error('Unknown workspace type: ' . $workspaceType);

            return null;
        }

        return $this->searchIndexConfigService->getIndexName($indexName);
    }

    private function evaluateExcludedPaths(array $allowedPaths, array $declinedPaths): array
    {
        $result = [];
        foreach ($declinedPaths as $path) {
            if ($this->pathService->containsSubPath($path, $allowedPaths)) {
                $result[] = $path;
            }

        }

        return $result;
    }

    private function evaluateExcludedFullPaths(array $allowedPaths, array $declinedPaths): array
    {
        $result = [];

        foreach ($declinedPaths as $path) {
            if (!$this->pathService->containsSubPath($path, $allowedPaths)) {
                $result[] = $path;
            }
        }

        return $result;
    }

    private function createNoWorkspaceAllowedQuery(): BoolQuery
    {
        return new BoolQuery([
            ConditionType::FILTER->value => [
                'term' => [
                    SystemField::FULL_PATH->getPath() => -1,
                ],
            ],
        ]);
    }

    private function getAllDeclinedParentPaths(array $declinedPaths, array $allowedPaths): array
    {
        if (empty($declinedPaths)) {
            return [];
        }

        $allowedParentPaths = $this->pathService->getAllParentPaths($allowedPaths, false);
        $declinedParentPaths = [];
        foreach ($allowedParentPaths as $allowedParentPath) {
            foreach ($declinedPaths as $declinedPath) {
                if ($allowedParentPath === $declinedPath ||
                    $this->pathService->isSubPath($allowedParentPath, $declinedPath)
                ) {
                    $declinedParentPaths[] = $allowedParentPath;
                }
            }
        }

        $declinedParentPaths = array_unique($declinedParentPaths);
        sort($declinedParentPaths);

        return $declinedParentPaths;
    }
}
