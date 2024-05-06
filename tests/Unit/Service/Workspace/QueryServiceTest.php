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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Workspace;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\AssetWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workspace\QueryService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workspace\WorkspaceServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Helper\WorkspaceHelper;
use Pimcore\Model\User;
use Pimcore\Model\User\Workspace;

/**
 * @internal
 */
final class QueryServiceTest extends Unit
{
    private WorkspaceHelper $workspaceHelper;

    public function _before(): void
    {
        $this->workspaceHelper = WorkspaceHelper::create();
    }

    public function testGetWorkspaceQueryWithUser(): void
    {
        $allowedPath = '/parentFolder';
        $workspaceService = $this->makeEmpty(
            WorkspaceServiceInterface::class,
            [
                'getUserWorkspaces' => [
                    new AssetWorkspace(
                        $this->workspaceHelper->getUserWorkspace(Workspace\Asset::class, '/')
                    ),
                    new AssetWorkspace(
                        $this->workspaceHelper->getUserWorkspace(Workspace\Asset::class, $allowedPath)
                    ),
                ],
                'getRoleWorkspaces' => [
                    new AssetWorkspace(
                        $this->workspaceHelper->getUserWorkspace(Workspace\Asset::class, $allowedPath)
                    ),
                ],
            ]
        );

        $permissionService = $this->makeEmpty(
            PermissionServiceInterface::class,
            [
                'checkWorkspacePermission' => function ($workspace) use ($allowedPath) {
                    return $workspace->getPath() === $allowedPath;
                },
            ]
        );
        $user = new User();
        $user->setRoles([1]);

        $query = $this->getWorkspaceQueryService(
            $workspaceService,
            $permissionService
        )->getWorkspaceQuery(
            AssetWorkspace::WORKSPACE_TYPE,
            $user,
            PermissionTypes::LIST->value
        );

        $this->assertEquals($this->getGenericWorkspaceQuery([$this, 'getExpectedWorkspaceQuery']), $query);
    }

    public function testGetWorkspaceQueryWithAdminUser(): void
    {
        $user = new User();
        $user->setAdmin(true);

        $query = $this->getWorkspaceQueryService()->getWorkspaceQuery(
            AssetWorkspace::WORKSPACE_TYPE,
            $user,
            PermissionTypes::LIST->value
        );

        $this->assertEquals(new BoolQuery(), $query);
    }

    public function testGetWorkspaceQueryWithNoUser(): void
    {
        $query = $this->getWorkspaceQueryService()->getWorkspaceQuery(
            AssetWorkspace::WORKSPACE_TYPE,
            null,
            PermissionTypes::LIST->value
        );

        $this->assertEquals($this->getGenericWorkspaceQuery([$this, 'getEmptyWorkspaceQuery']), $query);
    }

    public function testGetWorkspaceQueryWithUserWithoutWorkspaces(): void
    {
        $workspaceService = $this->makeEmpty(
            WorkspaceServiceInterface::class,
            [
                'getUserWorkspaces' => [],
                'getRoleWorkspaces' => [],
            ]
        );

        $query = $this->getWorkspaceQueryService(
            $workspaceService
        )->getWorkspaceQuery(
            AssetWorkspace::WORKSPACE_TYPE,
            null,
            PermissionTypes::LIST->value
        );

        $this->assertEquals($this->getGenericWorkspaceQuery([$this, 'getEmptyWorkspaceQuery']), $query);
    }

    private function getWorkspaceQueryService(
        ?WorkspaceServiceInterface $workspaceService = null,
        ?PermissionServiceInterface $permissionService = null
    ): QueryService {
        if(!$permissionService) {
            $permissionService = $this->makeEmpty(PermissionServiceInterface::class);
        }

        if (!$workspaceService) {
            $workspaceService = $this->makeEmpty(WorkspaceServiceInterface::class);
        }

        return new QueryService(
            $permissionService,
            $workspaceService
        );
    }

    private function getGenericWorkspaceQuery(callable $getMethod): BoolQuery
    {
        return (new BoolQuery())->addCondition(
            ConditionType::MUST->value,
            ['bool' => $getMethod()->toArray()]
        );

    }

    private function getEmptyWorkspaceQuery(): BoolQuery
    {
        return new BoolQuery([
            ConditionType::FILTER->value => [
                'term' => [
                    SystemField::FULL_PATH->getPath() => -1,
                ],
            ],
        ]);
    }

    private function getExpectedWorkspaceQuery(): BoolQuery
    {
        return new BoolQuery([
            ConditionType::SHOULD->value => [
                    [
                        'bool' => [
                            ConditionType::MUST_NOT->value => [
                                'term' => [
                                    SystemField::FULL_PATH->getPath() => '/',
                                ],
                            ],
                            ConditionType::SHOULD->value => [
                                'term' => [
                                    SystemField::FULL_PATH->getPath() => '/parentFolder',
                                ],
                            ],
                        ],
                    ],
                    [
                        'bool' => [
                            ConditionType::SHOULD->value => [
                                'term' => [
                                    SystemField::FULL_PATH->getPath() => '/parentFolder',
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );
    }
}
