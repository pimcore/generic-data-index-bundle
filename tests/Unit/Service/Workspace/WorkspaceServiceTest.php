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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Workspace;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\WorkspaceNotFoundException;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\AssetWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DocumentWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workspace\WorkspaceService;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserResolverInterface;
use Pimcore\Model\User;
use Pimcore\Model\User\Workspace;
use Pimcore\Model\User\Workspace\AbstractWorkspace;

/**
 * @internal
 */
final class WorkspaceServiceTest extends Unit
{
    private ?User\UserRole $role = null;

    public function testGetRelevantWorkspaces(): void
    {
        $workspaces = [
            $this->getUserWorkspace(Workspace\Asset::class, '/'),
            $this->getUserWorkspace(Workspace\Asset::class, '/parentFolder'),
            $this->getUserWorkspace(Workspace\Asset::class, '/anotherParent'),
        ];

        $relevantWorkspaces = $this->getWorkspaceService()->getRelevantWorkspaces(
            $workspaces,
            '/anotherParent/child'
        );

        $this->assertCount(2, $relevantWorkspaces);
        $this->assertSame('/anotherParent', $relevantWorkspaces[1]->getPath());
        $this->assertInstanceOf(AssetWorkspace::class, $relevantWorkspaces[0]);
        $this->assertInstanceOf(AssetWorkspace::class, $relevantWorkspaces[1]);
    }

    public function testGetRelevantWorkspacesWithInvalidType(): void
    {
        $workspaces = [
            $this->getUserWorkspace(Workspace\Asset::class, '/'),
            new MyTestWorkspace(),
        ];

        $this->expectException(WorkspaceNotFoundException::class);
        $this->expectExceptionMessage('Provided workspace has unknown type!');
        $this->getWorkspaceService()->getRelevantWorkspaces(
            $workspaces,
            '/anotherParent/child'
        );
    }

    public function testGetRoleWorkspaces(): void
    {
        $this->role = new User\Role();
        $this->role->setId(1);
        $this->role->setWorkspacesObject(
            [
                $this->getUserWorkspace(Workspace\DataObject::class, '/'),
            ]
        );

        $this->assertCount(
            1,
            $this->getWorkspaceService()->getRoleWorkspaces([$this->role->getId()], 'object')
        );
        $this->assertCount(
            0,
            $this->getWorkspaceService()->getRoleWorkspaces([$this->role->getId()], 'asset')
        );

        $this->role->setWorkspacesAsset(
            [
                $this->getUserWorkspace(Workspace\Asset::class, '/'),
                $this->getUserWorkspace(Workspace\Asset::class, '/parentFolder'),
            ]
        );

        $this->assertCount(
            2,
            $this->getWorkspaceService()->getRoleWorkspaces([$this->role->getId()], 'asset')
        );
    }

    public function testGetRoleWorkspacesWithNoRole(): void
    {
        $this->assertEmpty(
            $this->getWorkspaceService()->getRoleWorkspaces([], 'object')
        );
    }

    public function testGetDeepestWorkspaces(): void
    {
        $workSpaces = [
            new DocumentWorkspace($this->getUserWorkspace(
                Workspace\Document::class, '/'
            )),
            new DocumentWorkspace($this->getUserWorkspace(
                Workspace\Document::class, '/parentFolder'
            )),
            new DocumentWorkspace($this->getUserWorkspace(
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

    private function getUserWorkspace(string $type, string $path)
    {
        $workspace = new $type();
        $workspace->setCpath($path);

        return $workspace;
    }
}

class MyTestWorkspace extends AbstractWorkspace
{
    protected string $cpath = '/';
}
