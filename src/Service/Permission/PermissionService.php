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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Permission;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\BasePermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DocumentPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\AssetWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DataObjectWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DocumentWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\WorkspaceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\EventServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Workspace\WorkspaceServiceInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
final readonly class PermissionService implements PermissionServiceInterface
{
    private const SPECIAL_PERMISSIONS = [
        'localizedView',
        'localizedEdit',
    ];

    public function __construct(
        private EventServiceInterface $eventService,
        private LanguageServiceInterface $languageService,
        private WorkspaceServiceInterface $workspaceService,
    ) {
    }

    public function getAssetPermissions(
        AssetSearchResultItem $asset,
        ?User $user
    ): AssetPermissions {
        $permissions = new AssetPermissions();
        /** @var AssetPermissions $permissions */
        $permissions = $this->getPermissions(
            elementPath: $asset->getFullPath(),
            parentId: $asset->getParentId(),
            permissionsType: AssetWorkspace::WORKSPACE_TYPE,
            defaultPermissions: $permissions,
            user: $user
        );

        return $this->eventService->dispatchAssetSearchEvent($asset, $permissions)->getPermissions();
    }

    public function getDocumentPermissions(
        DocumentSearchResultItem $document,
        ?User $user
    ): DocumentPermissions {
        $permissions = new DocumentPermissions();
        /** @var DocumentPermissions $permissions */
        $permissions = $this->getPermissions(
            elementPath: $document->getFullPath(),
            parentId: $document->getParentId(),
            permissionsType: DocumentWorkspace::WORKSPACE_TYPE,
            defaultPermissions: $permissions,
            user: $user
        );

        return $this->eventService->dispatchDocumentSearchEvent($document, $permissions)->getPermissions();
    }

    public function getDataObjectPermissions(
        DataObjectSearchResultItem $object,
        ?User $user
    ): DataObjectPermissions {
        $permissions = new DataObjectPermissions();
        /** @var DataObjectPermissions $permissions */
        $permissions = $this->getPermissions(
            elementPath: $object->getFullPath(),
            parentId: $object->getParentId(),
            permissionsType: DataObjectWorkspace::WORKSPACE_TYPE,
            defaultPermissions: $permissions,
            user: $user,
        );

        return $this->eventService->dispatchDataObjectSearchEvent($object, $permissions)->getPermissions();
    }

    public function checkWorkspacePermission(
        WorkspaceInterface $workspace,
        string $permission
    ): bool {
        $permissions = $workspace->getPermissions();

        return $this->getPermissionValue($permissions, $permission);
    }

    public function getPermissionValue(
        AssetPermissions|DocumentPermissions|DataObjectPermissions $permissions,
        string $permission,
        ?string $permissionValueKey = null
    ): bool {
        $getter = 'is' . ucfirst($permission);
        if (!method_exists($permissions, $getter)) {
            return false;
        }

        $value = $permissions->$getter();
        if ($permissions instanceof DataObjectPermissions && !is_bool($value)) {
            return in_array(
                $permissionValueKey,
                $this->getSpecialPermissionValues($permissions, $permission),
                true
            );
        }

        return $value;
    }

    public function getSpecialPermissionValues(DataObjectPermissions $permissions, string $permission): array
    {
        if (!in_array($permission, self::SPECIAL_PERMISSIONS)) {
            return [];
        }

        $getter = 'is' . ucfirst($permission);
        $permissionValues = $permissions->$getter();
        if ($permissionValues === null) {
            return [];
        }

        return explode(',', $permissionValues);
    }

    private function getPermissions(
        string $elementPath,
        int $parentId,
        string $permissionsType,
        BasePermissions $defaultPermissions,
        ?User $user
    ): BasePermissions {
        $adminPermissions = $this->getAdminUserPermissions(
            $user,
            $defaultPermissions
        );

        if ($adminPermissions) {
            return $this->addRootNodePermissions($elementPath, $parentId, $adminPermissions);
        }

        $userWorkspaces = $this->workspaceService->getRelevantWorkspaces(
            $this->workspaceService->getUserWorkspaces($permissionsType, $user),
            $elementPath
        );
        $userRoleWorkspaces = [];
        if ($user) {
            $userRoleWorkspaces = $this->workspaceService->getUserRoleWorkspaces(
                $user,
                $permissionsType,
                $elementPath
            );
        }
        $permissions = $this->getPermissionsFromWorkspaces($userWorkspaces, $userRoleWorkspaces) ?? $defaultPermissions;

        return $this->addRootNodePermissions($elementPath, $parentId, $permissions);
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
            if (is_bool($value)) {
                $permissions->$setter(true);

                continue;
            }

            if (in_array($property, self::SPECIAL_PERMISSIONS, true)) {
                $permissions->$setter(implode(',', $this->languageService->getValidLanguages()));
            }
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

    private function addRootNodePermissions(
        string $fullPath,
        int $parentId,
        BasePermissions $permissions
    ): BasePermissions {
        if ($fullPath === '/' && $parentId === 0) {
            $permissions->setDelete(false);
            $permissions->setRename(false);
            if (method_exists($permissions, 'setUnpublish')) {
                $permissions->setUnpublish(false);
            }
        }

        return $permissions;
    }
}
