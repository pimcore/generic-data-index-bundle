<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Aggregation\Aggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search;
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
        $workspaceGroups = $this->getGroupedWorkspaces(
            $workspaceType,
            $user
        );

        if (empty($workspaceGroups)) {
            return $this->createNoWorkspaceAllowedQuery();
        }

        $workspacesQuery = new BoolQuery();

        foreach ($workspaceGroups as $group) {
            $workspacesQuery->addCondition(
                ConditionType::SHOULD->value,
                [
                    'bool' => $this->createWorkspacesGroupQuery($workspaceType, $group, $permission)->toArray(),
                ]
            );
        }

        return $workspacesQuery;
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

        if (!empty($userWorkspaces)) {
            $groupedWorkspaces[] = $userWorkspaces;
        }

        foreach ($user->getRoles() as $roleId) {
            $roleWorkspaces = $this->workspaceService->getRoleWorkspaces(
                $workspaceType,
                $roleId
            );

            if (!empty($roleWorkspaces)) {
                $groupedWorkspaces[] = $roleWorkspaces;
            }
        }

        return $groupedWorkspaces;
    }

    private function createWorkspacesGroupQuery(string $workspaceType, array $group, string $permission): BoolQuery
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
        $declinedPaths = $this->evaluateDeclinedPaths($workspaceType, $allowedPaths, $declinedPaths);

        if (count($allowedPaths) === 0) {
            return $this->createNoWorkspaceAllowedQuery();
        }

        $excludedPaths = $this->evaluateExcludedPaths($allowedPaths, $declinedPaths);
        $excludedFullPaths = $this->evaluateExcludedFullPaths($allowedPaths, $declinedPaths);

        $query = new BoolQuery();

        if (count($allowedPaths) > 0) {
            $allowedMainPaths = $this->pathService->removeSubPaths($allowedPaths);

            if(count($allowedMainPaths) === 1 && $allowedMainPaths[0] === '/') {
                $query->addCondition(
                    ConditionType::SHOULD->value,
                    [
                        'exists' => [
                            'field' => SystemField::FULL_PATH->getPath(),
                        ]
                    ]
                );
            } else {
                $query->addCondition(
                    ConditionType::SHOULD->value,
                    [
                        'terms' => [
                            SystemField::FULL_PATH->getPath() => $allowedPaths,
                        ],
                    ]
                );
            }


        }

        if (count($excludedPaths) > 0) {
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

        if (count($excludedFullPaths) > 0) {
            $query->addCondition(
                ConditionType::MUST_NOT->value,
                [
                    'terms' => [
                        SystemField::FULL_PATH->getPath() => $this->pathService->removeSubPaths($excludedFullPaths),
                    ],
                ]
            );
        }

        return $query;
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
                                    'lte' => $this->pathService->calculateLongestPathLevel($allowedSubPaths)
                                ],
                            ],
                        ]
                    ],
                    ConditionType::MUST_NOT->value => [
                        'terms' => [
                            SystemField::FULL_PATH->getPath() => $allowedSubPaths,
                        ],
                    ]
                ]);

                $boolQuery->addCondition(ConditionType::SHOULD->value, $subQuery->toArray(true));
            }
        }

        if (!$boolQuery->isEmpty()) {
            $search = (new Search())
                ->setSize(0)
                ->addQuery($boolQuery)
                ->addAggregation(new Aggregation('paths', [
                    "terms" => [
                        "field" => SystemField::PATH->getPath('keyword'),
                        "size" => 10000,
                    ]
                ]));

            $result = $this->searchIndexService->search($search, 'pimcore_asset');
            $buckets = $result->getAggregation('paths')?->getBuckets() ?? [];
            foreach ($buckets as $bucket) {
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
}
