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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Permission;

use Pimcore\Bundle\GenericDataIndexBundle\Event;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\BasePermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermission;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DocumentPermission;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\AssetWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DataObjectWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DocumentWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\WorkspaceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workspace\WorkspaceServiceInterface;
use Pimcore\Model\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class PermissionService implements PermissionServiceInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly WorkspaceServiceInterface $workspaceService,
    ) {
    }

    public function getAssetPermissions(
        AssetSearchResultItem $asset,
        ?User $user
    ): AssetPermissions {
        $permissions = new AssetPermissions();
        /** @var AssetPermissions $permissions */
        $permissions = $this->getPermissions(
            assetPath: $asset->getFullPath(),
            permissionsType: AssetWorkspace::WORKSPACE_TYPE,
            defaultPermissions: $permissions,
            user: $user
        ) ?? $permissions;

        $event = new Event\Asset\PermissionEvent($asset, $permissions);
        $this->eventDispatcher->dispatch($event);

        return $event->getPermissions();
    }

    public function getDocumentPermissions(
        string $documentPath,
        ?User $user
    ): DocumentPermission {
        $permissions = new DocumentPermission();
        /** @var DocumentPermission $permissions */
        $permissions = $this->getPermissions(
            assetPath: $documentPath,
            permissionsType: DocumentWorkspace::WORKSPACE_TYPE,
            defaultPermissions: $permissions,
            user: $user
        ) ?? $permissions;

        //ToDo - add event dispatching

        return $permissions;
    }

    public function getDataObjectPermissions(
        string $objectPath,
        ?User $user
    ): DataObjectPermission {
        $permissions = new DataObjectPermission();
        /** @var DataObjectPermission $permissions */
        $permissions = $this->getPermissions(
            assetPath: $objectPath,
            permissionsType: DataObjectWorkspace::WORKSPACE_TYPE,
            defaultPermissions: $permissions,
            user: $user,
        ) ?? $permissions;

        $searchResult = null; // ToDo Change when the search service is implemented
        $event = new Event\DataObject\PermissionEvent($searchResult, $permissions);
        $this->eventDispatcher->dispatch($event);

        return $event->getPermissions();
    }

    public function checkWorkspacePermission(
        WorkspaceInterface $workspace,
        string $permission
    ): bool {
        $permissions = $workspace->getPermissions();

        return $this->getPermissionValue($permissions, $permission);
    }

    public function getPermissionValue(BasePermissions $permissions, string $permission): bool
    {
        $getter = 'is' . ucfirst($permission);
        if (method_exists($permissions, $getter)) {
            return $permissions->$getter();
        }

        return false;
    }

    private function getPermissions(
        string $assetPath,
        string $permissionsType,
        BasePermissions $defaultPermissions,
        ?User $user
    ): ?BasePermissions {
        $adminPermissions = $this->getAdminUserPermissions(
            $user,
            $defaultPermissions
        );

        if ($adminPermissions) {
            return $adminPermissions;
        }

        $userWorkspaces = $this->workspaceService->getRelevantWorkspaces(
            $this->workspaceService->getUserWorkspaces($permissionsType, $user),
            $assetPath
        );
        $userRoleWorkspaces = [];
        if ($user) {
            $userRoleWorkspaces = $this->workspaceService->getUserRoleWorkspaces(
                $user,
                $permissionsType,
                $assetPath
            );
        }

        return $this->getPermissionsFromWorkspaces($userWorkspaces, $userRoleWorkspaces);
    }

    private function getAdminUserPermissions(
        ?User $user,
        BasePermissions $permissions
    ): ?BasePermissions {
        if (!$user?->isAdmin()) {
            return null;
        }

        $properties = $permissions->getClassProperties();
        foreach ($properties as $property => $value) {
            $setter = 'set' . ucfirst($property);
            $permissions->$setter(true);
        }

        return $permissions;
    }

    private function getPermissionsFromWorkspaces(
        array $userWorkspaces,
        array $roleWorkspaces
    ): ?BasePermissions {
        if (empty($userWorkspaces) && empty($roleWorkspaces)) {
            return null;
        }

        if (empty($roleWorkspaces)) {
            return $this->workspaceService->getDeepestWorkspace($userWorkspaces)->getPermissions();
        }

        $userWorkspace = $this->workspaceService->getDeepestWorkspace($userWorkspaces);
        $roleWorkspace = $this->workspaceService->getDeepestWorkspace($roleWorkspaces);

        if ($roleWorkspace->getPath() !== $userWorkspace->getPath()) {
            return $this->workspaceService->getDeepestWorkspace(
                [$userWorkspace, $roleWorkspace]
            )->getPermissions();
        }

        return $this->addRelevantRolePermissions(
            $userWorkspace,
            $roleWorkspace
        );
    }

    private function addRelevantRolePermissions(
        WorkspaceInterface $userWorkspace,
        WorkspaceInterface $roleWorkspace
    ): BasePermissions {
        $rolePermissions = $roleWorkspace->getPermissions();
        $workspacePermissions = $userWorkspace->getPermissions();
        $properties = $roleWorkspace->getPermissions()->getClassProperties();
        foreach ($properties as $property => $value) {
            $setter = 'set' . ucfirst($property);
            $getter = 'is' . ucfirst($property);
            if (method_exists($rolePermissions, $getter) &&
                method_exists($workspacePermissions, $setter) &&
                $rolePermissions->$getter() === true
            ) {
                $workspacePermissions->$setter(true);
            }
        }

        return $workspacePermissions;
    }
}
