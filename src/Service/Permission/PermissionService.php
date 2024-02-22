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

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermission;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DocumentPermission;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\AssetWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DataObjectWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DocumentWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\WorkspaceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workspace\WorkspaceServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Lib\Tools\Authentication\AuthenticationResolverInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
final class PermissionService implements PermissionServiceInterface
{
    private ?User $user;

    public function __construct(
        private readonly AuthenticationResolverInterface $authenticationResolver,
        private readonly WorkspaceServiceInterface $workspaceService
    ) {
        $pimcoreUser = $this->authenticationResolver->authenticateSession();
        $this->user = $pimcoreUser;
    }

    /**
     * @throws Exception
     */
    public function getAssetPermissions(string $assetPath): AssetPermissions
    {
        if (!$this->user) {
            return new AssetPermissions();
        }

        /** @var AssetPermissions $permissions */
        $permissions = $this->getPermissions(
            assetPath: $assetPath,
            permissionsType: AssetWorkspace::WORKSPACE_TYPE
        );

        return $permissions;
    }

    /**
     * @throws Exception
     */
    public function getDocumentPermissions(string $assetPath): DocumentPermission
    {
        if (!$this->user) {
            return new DocumentPermission();
        }

        /** @var DocumentPermission $permissions */
        $permissions = $this->getPermissions(
            assetPath: $assetPath,
            permissionsType: DocumentWorkspace::WORKSPACE_TYPE
        );

        return $permissions;
    }

    /**
     * @throws Exception
     */
    public function getDataObjectPermissions(string $assetPath): DataObjectPermission
    {
        if (!$this->user) {
            return new DataObjectPermission();
        }

        /** @var DataObjectPermission $permissions */
        $permissions = $this->getPermissions(
            assetPath: $assetPath,
            permissionsType: DataObjectWorkspace::WORKSPACE_TYPE
        );

        return $permissions;
    }

    /**
     * @throws Exception
     */
    private function getPermissions(
        string $assetPath,
        string $permissionsType
    ): AssetPermissions|DocumentPermission|DataObjectPermission {
        $roleWorkspace = null;
        $roleIds = $this->user->getRoles();
        if (!empty($roleIds)) {
            $roleWorkspaces = $this->workspaceService->getRelevantWorkspaces(
                $this->workspaceService->getRoleWorkspaces($roleIds, $permissionsType),
                $assetPath
            );
            if (!empty($roleWorkspaces)) {
                $roleWorkspace = $this->workspaceService->getDeepestWorkspace($roleWorkspaces);
            }
        }
        $workspaceGetter = 'getWorkspaces' . ucfirst($permissionsType);
        $workspaces = $this->workspaceService->getRelevantWorkspaces(
            $this->user->$workspaceGetter(),
            $assetPath
        );
        $workspace = $this->workspaceService->getDeepestWorkspace($workspaces);

        return $this->getPermissionsFromWorkspaces($workspace, $roleWorkspace);
    }

    private function getPermissionsFromWorkspaces(
        WorkspaceInterface $workspace,
        ?WorkspaceInterface $roleWorkspace = null
    ): AssetPermissions|DocumentPermission|DataObjectPermission {
        if (!$roleWorkspace) {
            return $workspace->getPermissions();
        }

        if ($roleWorkspace->getPath() !== $workspace->getPath()) {
            return $this->workspaceService->getDeepestWorkspace(
                [$workspace, $roleWorkspace]
            )->getPermissions();
        }

        $rolePermissions = $roleWorkspace->getPermissions();
        $workspacePermissions = $workspace->getPermissions();
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
