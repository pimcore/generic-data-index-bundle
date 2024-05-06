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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Permission;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\PermissionEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\AssetWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DataObjectWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DocumentWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workspace\WorkspaceService;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserResolver;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserResolverInterface;
use Pimcore\Model\User;
use Pimcore\Model\User\Workspace;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class PermissionServiceTest extends Unit
{
    private const DEFAULT_VALUE = true;

    private User $user;

    private ?User\UserRole $role = null;

    private AssetSearchResultItem $assetSearchResult;

    private DataObjectSearchResultItem $dataObjectSearchResult;

    public function _before(): void
    {
        $this->user = new User();
        $this->assetSearchResult = new AssetSearchResultItem();
        $this->dataObjectSearchResult = new DataObjectSearchResultItem();
    }

    public function testAssetPermissionWithUserOnRoot(): void
    {
        $this->user->setWorkspacesAsset([$this->getWorkspace(
            path: '/',
            permissions: ['view', 'list'],
            type: AssetWorkspace::WORKSPACE_TYPE
        )]);
        $assetPermission = $this->getPermissionServiceWithUser()->getAssetPermissions(
            $this->assetSearchResult->setFullPath('/'),
            $this->user
        );

        $this->assertTrue($assetPermission->isView());
        $this->assertTrue($assetPermission->isList());
        $this->assertFalse($assetPermission->isDelete());
    }

    public function testAssetPermissionWithUserOnCustomPath(): void
    {
        $this->user->setWorkspacesAsset(
            [
                $this->getWorkspace(
                    path: '/',
                    permissions: ['view', 'list', 'rename'],
                    type: AssetWorkspace::WORKSPACE_TYPE
                ),
                $this->getWorkspace(
                    path: '/parentFolder',
                    permissions: ['view', 'list', 'publish'],
                    type: AssetWorkspace::WORKSPACE_TYPE
                ),
                $this->getWorkspace(
                    path: '/parentFolder/childFolder',
                    permissions: ['view', 'list', 'delete'],
                    type: AssetWorkspace::WORKSPACE_TYPE
                ),
            ]
        );

        $assetPermission = $this->getPermissionServiceWithUser()->getAssetPermissions(
            $this->assetSearchResult->setFullPath('/parentFolder/childFolder'),
            $this->user

        );

        $this->assertTrue($assetPermission->isView());
        $this->assertTrue($assetPermission->isList());
        $this->assertTrue($assetPermission->isDelete());
        $this->assertFalse($assetPermission->isPublish());
        $this->assertFalse($assetPermission->isRename());
    }

    public function testAssetPermissionWithUserAndRoleOnCustomPath(): void
    {
        $this->role = new User\Role();
        $this->role->setWorkspacesAsset(
            [
                $this->getWorkspace(
                    path: '/parentFolder/childFolder/testFolder',
                    permissions: ['view', 'publish', 'delete'],
                    type: AssetWorkspace::WORKSPACE_TYPE
                ),
            ]
        );
        $this->user->setRoles([$this->role->getId()]);
        $this->user->setWorkspacesAsset(
            [
                $this->getWorkspace(
                    path: '/',
                    permissions: ['view', 'list', 'rename'],
                    type: AssetWorkspace::WORKSPACE_TYPE
                ),
                $this->getWorkspace(
                    path: '/parentFolder',
                    permissions: ['view', 'publish'],
                    type: AssetWorkspace::WORKSPACE_TYPE
                ),
                $this->getWorkspace(
                    path: '/parentFolder/childFolder',
                    permissions: ['view', 'list'],
                    type: AssetWorkspace::WORKSPACE_TYPE
                ),
            ]
        );

        $assetPermission = $this->getPermissionServiceWithUser()->getAssetPermissions(
            $this->assetSearchResult->setFullPath('/parentFolder/childFolder/testFolder'),
            $this->user
        );

        $this->assertTrue($assetPermission->isView());
        $this->assertTrue($assetPermission->isPublish());
        $this->assertTrue($assetPermission->isDelete());
        $this->assertFalse($assetPermission->isList());
        $this->assertFalse($assetPermission->isRename());
    }

    public function testAssetPermissionWithoutUserOnRoot(): void
    {
        $permissionService = $this->getPermissionServiceWithoutUser();
        $assetPermission = $permissionService->getAssetPermissions(
            $this->assetSearchResult->setFullPath('/'),
            null
        );
        $this->assertSame(self::DEFAULT_VALUE, $assetPermission->isList());
        $this->assertSame(self::DEFAULT_VALUE, $assetPermission->isView());
        $this->assertSame(self::DEFAULT_VALUE, $assetPermission->isRename());
    }

    public function testObjectPermissionWithUserOnRoot(): void
    {
        $this->user->setWorkspacesObject([$this->getWorkspace(
            path: '/',
            permissions: ['view', 'list', 'publish', 'unpublish'],
            type: DataObjectWorkspace::WORKSPACE_TYPE
        )]);
        $permission = $this->getPermissionServiceWithUser()->getDataObjectPermissions(
            $this->dataObjectSearchResult->setFullPath('/'),
            $this->user
        );

        $this->assertTrue($permission->isView());
        $this->assertTrue($permission->isList());
        $this->assertTrue($permission->isPublish());
        $this->assertTrue($permission->isUnpublish());
        $this->assertFalse($permission->isDelete());
    }

    public function testObjectPermissionWithUserOnCustomPath(): void
    {
        $this->user->setWorkspacesObject(
            [
                $this->getWorkspace(
                    path: '/',
                    permissions: ['view', 'list', 'rename'],
                    type: DataObjectWorkspace::WORKSPACE_TYPE
                ),
                $this->getWorkspace(
                    path: '/parentFolder',
                    permissions: ['view', 'list', 'publish'],
                    type: DataObjectWorkspace::WORKSPACE_TYPE
                ),
                $this->getWorkspace(
                    path: '/parentFolder/childFolder',
                    permissions: ['view', 'publish', 'unpublish'],
                    type: DataObjectWorkspace::WORKSPACE_TYPE
                ),
            ]
        );

        $permissions = $this->getPermissionServiceWithUser()->getDataObjectPermissions(
            $this->dataObjectSearchResult->setFullPath('/parentFolder/childFolder'),
            $this->user
        );

        $this->assertTrue($permissions->isView());
        $this->assertFalse($permissions->isList());
        $this->assertFalse($permissions->isDelete());
        $this->assertTrue($permissions->isPublish());
        $this->assertTrue($permissions->isUnpublish());
    }

    public function testObjectPermissionWithUserAndRoleOnCustomPath(): void
    {
        $this->role = new User\Role();
        $this->role->setWorkspacesObject(
            [
                $this->getWorkspace(
                    path: '/parentFolder/childFolder',
                    permissions: ['publish', 'unpublish'],
                    type: DataObjectWorkspace::WORKSPACE_TYPE
                ),
            ]
        );

        $this->user->setRoles([$this->role->getId()]);
        $this->user->setWorkspacesObject(
            [
                $this->getWorkspace(
                    path: '/',
                    permissions: ['view', 'list', 'rename'],
                    type: DataObjectWorkspace::WORKSPACE_TYPE
                ),
                $this->getWorkspace(
                    path: '/parentFolder',
                    permissions: ['view', 'list', 'save'],
                    type: DataObjectWorkspace::WORKSPACE_TYPE
                ),
                $this->getWorkspace(
                    path: '/parentFolder/childFolder',
                    permissions: ['view', 'list'],
                    type: DataObjectWorkspace::WORKSPACE_TYPE
                ),
            ]
        );

        $permissions = $this->getPermissionServiceWithUser()->getDataObjectPermissions(
            $this->dataObjectSearchResult->setFullPath('/parentFolder/childFolder'),
            $this->user
        );

        $this->assertTrue($permissions->isView());
        $this->assertTrue($permissions->isList());
        $this->assertTrue($permissions->isPublish());
        $this->assertTrue($permissions->isUnpublish());
        $this->assertFalse($permissions->isSave());
        $this->assertFalse($permissions->isRename());
    }

    public function testObjectPermissionWithoutUserOnRoot(): void
    {
        $permissionService = $this->getPermissionServiceWithoutUser();
        $permission = $permissionService->getDataObjectPermissions(
            $this->dataObjectSearchResult->setFullPath('/'),
            null
        );

        $this->assertSame(self::DEFAULT_VALUE, $permission->isList());
        $this->assertSame(self::DEFAULT_VALUE, $permission->isView());
        $this->assertSame(self::DEFAULT_VALUE, $permission->isCreate());
        $this->assertNull($permission->isLocalizedEdit());
    }

    public function testDocumentPermissionWithUserOnRoot(): void
    {
        $this->user->setWorkspacesDocument([$this->getWorkspace(
            path: '/',
            permissions: ['view', 'save', 'publish', 'unpublish'],
            type: DocumentWorkspace::WORKSPACE_TYPE
        )]);
        $permission = $this->getPermissionServiceWithUser()->getDocumentPermissions(
            '/',
            $this->user
        );

        $this->assertTrue($permission->isView());
        $this->assertTrue($permission->isSave());
        $this->assertTrue($permission->isPublish());
        $this->assertTrue($permission->isUnpublish());
        $this->assertFalse($permission->isList());
    }

    public function testDocumentPermissionWithUserOnCustomPath(): void
    {
        $this->user->setWorkspacesDocument(
            [
                $this->getWorkspace(
                    path: '/',
                    permissions: ['view', 'list'],
                    type: DocumentWorkspace::WORKSPACE_TYPE
                ),
                $this->getWorkspace(
                    path: '/parentFolder',
                    permissions: ['view', 'list', 'save', 'publish'],
                    type: DocumentWorkspace::WORKSPACE_TYPE
                ),
                $this->getWorkspace(
                    path: '/parentFolder/childFolder',
                    permissions: ['view', 'delete', 'unpublish'],
                    type: DocumentWorkspace::WORKSPACE_TYPE
                ),
            ]
        );

        $permissions = $this->getPermissionServiceWithUser()->getDocumentPermissions(
            '/parentFolder/testFolder',
            $this->user
        );

        $this->assertTrue($permissions->isView());
        $this->assertTrue($permissions->isList());
        $this->assertTrue($permissions->isSave());
        $this->assertTrue($permissions->isPublish());
        $this->assertFalse($permissions->isUnpublish());
        $this->assertFalse($permissions->isDelete());
    }

    public function testDocumentPermissionWithUserAndRoleOnCustomPath(): void
    {
        $this->role = new User\Role();
        $this->role->setWorkspacesDocument(
            [
                $this->getWorkspace(
                    path: '/parentFolder',
                    permissions: ['publish', 'unpublish'],
                    type: DocumentWorkspace::WORKSPACE_TYPE
                ),
            ]
        );
        $this->user->setRoles([$this->role->getId()]);
        $this->user->setWorkspacesDocument(
            [
                $this->getWorkspace(
                    path: '/',
                    permissions: ['view', 'list', 'rename'],
                    type: DocumentWorkspace::WORKSPACE_TYPE
                ),
                $this->getWorkspace(
                    path: '/parentFolder',
                    permissions: ['view', 'list', 'save'],
                    type: DocumentWorkspace::WORKSPACE_TYPE
                ),
                $this->getWorkspace(
                    path: '/parentFolder/childFolder',
                    permissions: ['view', 'list'],
                    type: DocumentWorkspace::WORKSPACE_TYPE
                ),
            ]
        );

        $permissions = $this->getPermissionServiceWithUser()->getDocumentPermissions(
            '/parentFolder/childFolder',
            $this->user
        );

        $this->assertTrue($permissions->isView());
        $this->assertTrue($permissions->isList());
        $this->assertFalse($permissions->isPublish());
        $this->assertFalse($permissions->isUnpublish());
        $this->assertFalse($permissions->isSave());
        $this->assertFalse($permissions->isRename());
    }

    public function testDocumentPermissionWithoutUserOnRoot(): void
    {
        $permissionService = $this->getPermissionServiceWithoutUser();
        $permission = $permissionService->getDocumentPermissions('/', null);

        $this->assertSame(self::DEFAULT_VALUE, $permission->isList());
        $this->assertSame(self::DEFAULT_VALUE, $permission->isView());
        $this->assertSame(self::DEFAULT_VALUE, $permission->isSave());
        $this->assertSame(self::DEFAULT_VALUE, $permission->isPublish());
    }

    public function testCheckWorkspacePermission(): void
    {
        $permissionService = $this->getPermissionServiceWithoutUser();
        $workspace = new DocumentWorkspace($this->getWorkspace(
            path: '/',
            permissions: ['view', 'list', 'rename'],
            type: DocumentWorkspace::WORKSPACE_TYPE
        ));
        $this->assertTrue($permissionService->checkWorkspacePermission($workspace, PermissionTypes::VIEW->value));
        $this->assertTrue($permissionService->checkWorkspacePermission($workspace, PermissionTypes::LIST->value));
        $this->assertTrue($permissionService->checkWorkspacePermission($workspace, PermissionTypes::RENAME->value));
        $this->assertFalse($permissionService->checkWorkspacePermission($workspace, PermissionTypes::DELETE->value));
        $this->assertFalse($permissionService->checkWorkspacePermission($workspace, PermissionTypes::PUBLISH->value));
    }

    public function testPermissionsWithAdminUserOnRoot(): void
    {
        $this->user->setAdmin(true);
        $permissionService = $this->getPermissionServiceWithUser();
        $permission = $permissionService->getAssetPermissions(
            $this->assetSearchResult->setFullPath('/'),
            $this->user
        );
        $properties = $permission->getClassProperties();
        foreach ($properties as $property => $value) {
            $getter = 'is' . ucfirst($property);
            $this->assertTrue($permission->$getter());
        }
    }

    public function testPermissionsWitAdminUserOnCustomPath(): void
    {
        $this->user->setAdmin(true);
        $permissionService = $this->getPermissionServiceWithUser();
        $permission = $permissionService->getAssetPermissions(
            $this->assetSearchResult->setFullPath('/Parent/Child'),
            $this->user
        );

        $properties = $permission->getClassProperties();
        foreach ($properties as $property => $value) {
            $getter = 'is' . ucfirst($property);
            $this->assertTrue($permission->$getter());
        }
    }

    public function testEventDispatcherCall(): void
    {
        $eventDispatcher = $this->makeEmpty(EventDispatcherInterface::class, [
            'dispatch' => Expected::exactly(1, static function () {
                return new PermissionEvent(new AssetSearchResultItem(), new AssetPermissions());
            }),
        ]);
        $permissionService = new PermissionService($eventDispatcher, new WorkspaceService(new UserResolver()));
        $permissionService->getAssetPermissions(
            $this->assetSearchResult->setFullPath('/'),
            $this->user
        );
    }

    /**
     * @throws Exception
     */
    private function getWorkspace(
        string $path,
        array $permissions,
        string $type
    ): Workspace\Asset|Workspace\DataObject|Workspace\Document {
        $workspace = match(true) {
            $type === AssetWorkspace::WORKSPACE_TYPE => new Workspace\Asset(),
            $type === DataObjectWorkspace::WORKSPACE_TYPE => new Workspace\DataObject(),
            $type === DocumentWorkspace::WORKSPACE_TYPE => new Workspace\Document(),
            default => throw new Exception('wrong workspace type provided')
        };
        $workspace->setCpath($path);
        foreach ($permissions as $permission) {
            $setter = 'set' . ucfirst($permission);
            $workspace->$setter(true);
        }

        return $workspace;
    }

    private function getPermissionServiceWithUser(): PermissionService
    {
        return new PermissionService(
            $this->makeEmpty(EventDispatcherInterface::class),
            new WorkspaceService(
                $this->makeEmpty(UserResolverInterface::class, [
                    'getUserRoleById' => $this->role,
                ])
            )
        );
    }

    private function getPermissionServiceWithoutUser(): PermissionService
    {
        return new PermissionService(
            $this->makeEmpty(EventDispatcherInterface::class),
            new WorkspaceService(
                new UserResolver()
            )
        );
    }
}
