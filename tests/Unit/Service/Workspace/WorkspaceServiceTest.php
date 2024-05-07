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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\WorkspaceNotFoundException;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\AssetWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DataObjectWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DocumentWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workspace\WorkspaceService;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Helper\WorkspaceHelper;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserResolverInterface;
use Pimcore\Model\User;
use Pimcore\Model\User\Workspace;

/**
 * @internal
 */
final class WorkspaceServiceTest extends Unit
{
    private ?User\UserRole $role = null;

    private WorkspaceHelper $workspaceHelper;

    public function _before(): void
    {
        $this->workspaceHelper = WorkspaceHelper::create();
    }

    public function testGetUserWorkspaces(): void
    {
        $workspaces = [
            $this->workspaceHelper->getUserWorkspace(Workspace\Asset::class, '/'),
            $this->workspaceHelper->getUserWorkspace(Workspace\Asset::class, '/parentFolder'),
            $this->workspaceHelper->getUserWorkspace(Workspace\Asset::class, '/anotherParent'),
        ];

        $user = new User();
        $user->setWorkspacesAsset($workspaces);
        $workspaces = $this->getWorkspaceService()->getUserWorkspaces('asset', $user);

        $this->assertCount(3, $workspaces);
        $this->assertInstanceOf(AssetWorkspace::class, $workspaces[0]);
        $this->assertInstanceOf(AssetWorkspace::class, $workspaces[1]);
        $this->assertInstanceOf(AssetWorkspace::class, $workspaces[2]);
    }

    public function testGetUserWorkspacesWithoutUser(): void
    {
        $workspaces = $this->getWorkspaceService()->getUserWorkspaces('asset', null);
        $this->assertEmpty($workspaces);
    }

    public function testGetUserRoleWorkspaces(): void
    {
        $this->role = new User\Role();
        $this->role->setId(1);
        $this->role->setWorkspacesObject(
            [
                $this->workspaceHelper->getUserWorkspace(Workspace\DataObject::class, '/'),
                $this->workspaceHelper->getUserWorkspace(Workspace\DataObject::class, '/parent/'),
                $this->workspaceHelper->getUserWorkspace(Workspace\DataObject::class, '/parent/child'),
            ]
        );

        $user = new User();
        $user->setRoles([1]);
        $workspaceService = $this->getWorkspaceService();
        $path = '/parent/child';
        $workspaces = $workspaceService->getUserRoleWorkspaces(
            $user,
            DataObjectWorkspace::WORKSPACE_TYPE,
            $path
        );

        $this->assertInstanceOf(DataObjectWorkspace::class, $workspaces[0]);
        $this->assertInstanceOf(DataObjectWorkspace::class, $workspaces[1]);
        $this->assertInstanceOf(DataObjectWorkspace::class, $workspaces[2]);
        $this->assertCount(3, $workspaces);

        $path = '/';
        $workspaces = $workspaceService->getUserRoleWorkspaces(
            $user,
            DataObjectWorkspace::WORKSPACE_TYPE,
            $path
        );
        $this->assertCount(1, $workspaces);
    }

    public function testGetUserRoleWorkspacesWithNoRole(): void
    {
        $user = new User();
        $user->setRoles([]);
        $workspaceService = $this->getWorkspaceService();
        $path = '/parent/child';
        $workspaces = $workspaceService->getUserRoleWorkspaces(
            $user,
            DataObjectWorkspace::WORKSPACE_TYPE,
            $path
        );
        $this->assertEmpty($workspaces);
    }

    public function testGetUserRoleWorkspacesWithWrongType(): void
    {
        $this->role = new User\Role();
        $this->role->setId(1);
        $this->role->setWorkspacesAsset(
            [
                $this->workspaceHelper->getUserWorkspace(Workspace\Asset::class, '/'),
            ]
        );

        $user = new User();
        $user->setRoles([1]);
        $workspaceService = $this->getWorkspaceService();
        $path = '/parent/child';
        $workspaces = $workspaceService->getUserRoleWorkspaces(
            $user,
            DataObjectWorkspace::WORKSPACE_TYPE,
            $path
        );
        $this->assertEmpty($workspaces);
    }

    public function testGetRoleWorkspaces(): void
    {
        $this->role = new User\Role();
        $this->role->setId(1);
        $this->role->setWorkspacesObject(
            [
                $this->workspaceHelper->getUserWorkspace(Workspace\DataObject::class, '/'),
            ]
        );

        $this->assertCount(
            1,
            $this->getWorkspaceService()->getRoleWorkspaces('object', $this->role->getId())
        );
        $this->assertCount(
            0,
            $this->getWorkspaceService()->getRoleWorkspaces('asset', $this->role->getId())
        );

        $this->role->setWorkspacesAsset(
            [
                $this->workspaceHelper->getUserWorkspace(Workspace\Asset::class, '/'),
                $this->workspaceHelper->getUserWorkspace(Workspace\Asset::class, '/parentFolder'),
            ]
        );

        $this->assertCount(
            2,
            $this->getWorkspaceService()->getRoleWorkspaces('asset', $this->role->getId())
        );
    }

    public function testGetRoleWorkspacesWithInvalidRole(): void
    {
        $this->assertEmpty(
            $this->getWorkspaceService()->getRoleWorkspaces('object', 10)
        );
    }

    public function testGetRelevantWorkspaces(): void
    {
        $workspaces = [
            new AssetWorkspace($this->workspaceHelper->getUserWorkspace(
                Workspace\Asset::class, '/')
            ),
            new AssetWorkspace($this->workspaceHelper->getUserWorkspace(
                Workspace\Asset::class, '/parentFolder')
            ),
            new AssetWorkspace($this->workspaceHelper->getUserWorkspace(
                Workspace\Asset::class, '/anotherParent')
            ),
        ];

        $relevantWorkspaces = $this->getWorkspaceService()->getRelevantWorkspaces(
            $workspaces,
            '/anotherParent/child'
        );

        $this->assertCount(2, $relevantWorkspaces);
        $this->assertSame('/anotherParent', $relevantWorkspaces[1]->getPath());
    }

    public function testGetRelevantWorkspacesWithInvalidType(): void
    {
        $workspaces = [
            $this->workspaceHelper->getUserWorkspace(Workspace\Asset::class, '/'),
        ];

        $this->expectException(WorkspaceNotFoundException::class);
        $this->expectExceptionMessage('Provided workspace has unknown type!');
        $this->getWorkspaceService()->getRelevantWorkspaces(
            $workspaces,
            '/anotherParent/child'
        );
    }

    public function testGetDeepestWorkspaces(): void
    {
        $workSpaces = [
            new DocumentWorkspace($this->workspaceHelper->getUserWorkspace(
                Workspace\Document::class, '/'
            )),
            new DocumentWorkspace($this->workspaceHelper->getUserWorkspace(
                Workspace\Document::class, '/parentFolder'
            )),
            new DocumentWorkspace($this->workspaceHelper->getUserWorkspace(
                Workspace\Document::class, '/parentFolder/ChildFolder'
            )),
        ];

        $deepestWorkspace = $this->getWorkspaceService()->getDeepestWorkspace($workSpaces);

        $this->assertInstanceOf(DocumentWorkspace::class, $deepestWorkspace);
        $this->assertSame('/parentFolder/ChildFolder', $deepestWorkspace->getPath());
    }

    private function getWorkspaceService(): WorkspaceService
    {
        return new WorkspaceService(
            $this->makeEmpty(UserResolverInterface::class, [
                'getUserRoleById' => $this->role,
            ])
        );
    }
}
