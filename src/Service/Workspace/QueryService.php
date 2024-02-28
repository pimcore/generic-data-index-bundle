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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\WorkspaceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
final class QueryService implements QueryServiceInterface
{
    public function __construct(
        private readonly PermissionServiceInterface $permissionService,
        private readonly WorkspaceServiceInterface $workspaceService
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
                    'bool' => $this->createWorkspacesGroupQuery($group, $permission)->toArray(),
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

    private function createWorkspacesGroupQuery(array $group, string $permission): BoolQuery
    {
        $hasAllowedWorkspaces = false;
        foreach ($group as $workspace) {
            if ($this->permissionService->checkWorkspacePermission($workspace, $permission)) {
                $hasAllowedWorkspaces = true;

                break;
            }
        }

        if (!$hasAllowedWorkspaces) {
            return $this->createNoWorkspaceAllowedQuery();
        }

        $fullPathField = SystemField::FULL_PATH->getPath();
        $query = new BoolQuery();

        /** @var WorkspaceInterface $workspace */
        foreach ($group as $workspace) {
            if ($this->permissionService->checkWorkspacePermission($workspace, $permission) &&
                $workspace->getPath() === '/') {
                // path hierarchy tokenizer is not able to find the root path, therefore this dummy query is added
                $query->addCondition(
                    type: ConditionType::FILTER->value,
                    params: [
                        ConditionType::EXISTS->value => [
                            'field' => $fullPathField,
                        ],
                    ]
                );

                continue;
            }

            $type = ConditionType::SHOULD->value;
            $params = [
                'term' => [
                    $fullPathField => $workspace->getPath(),
                ],
            ];

            if (!$this->permissionService->checkWorkspacePermission($workspace, $permission)) {
                $type = ConditionType::MUST_NOT->value;
            }

            $query->addCondition(
                $type,
                $params
            );
        }

        return $query;
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
