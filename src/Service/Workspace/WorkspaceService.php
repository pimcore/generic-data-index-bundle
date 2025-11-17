<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\WorkspaceNotFoundException;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\AssetWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DataObjectWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DocumentWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\WorkspaceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserRoleResolverInterface;
use Pimcore\Model\User;
use Pimcore\Model\User\Workspace;

/**
 * @internal
 */
final readonly class WorkspaceService implements WorkspaceServiceInterface
{
    public function __construct(
        private UserRoleResolverInterface $userRoleResolver
    ) {
    }

    public function getUserWorkspaces(
        string $workspaceType,
        ?User $user
    ): array {
        if (!$user) {
            return [];
        }
        $workspaceGetter = 'getWorkspaces' . ucfirst($workspaceType);
        $workspaces =  $user->$workspaceGetter();

        return $this->getWorkspacesObject($workspaces);
    }

    public function getUserRoleWorkspaces(
        User $user,
        string $workspaceType,
        string $path
    ): array {
        $userRoleWorkspaces = [];
        $roles = $user->getRoles();
        foreach ($roles as $roleId) {
            $roleWorkspaces = $this->getRoleWorkspaces($workspaceType, $roleId);
            foreach ($roleWorkspaces as $roleWorkspace) {
                $userRoleWorkspaces[] = $roleWorkspace;
            }
        }

        if (!empty($userRoleWorkspaces)) {
            $userRoleWorkspaces = $this->getRelevantWorkspaces(
                $userRoleWorkspaces,
                $path
            );
        }

        return $userRoleWorkspaces;
    }

    public function getRoleWorkspaces(string $type, int $roleId): array
    {
        $workspaces = [];

        $getter = 'getWorkspaces' . ucfirst($type);
        $roleWorkspaces = $this->userRoleResolver->getById($roleId)?->$getter() ?? [];
        foreach ($roleWorkspaces as $roleWorkspace) {
            $workspaces[] = $roleWorkspace;
        }

        return $this->getWorkspacesObject($workspaces);
    }

    public function getRelevantWorkspaces(
        array $userWorkspaces,
        string $path
    ): array {
        $workspaces = [];
        foreach ($userWorkspaces as $userWorkspace) {
            if (!$userWorkspace instanceof WorkspaceInterface) {
                throw new WorkspaceNotFoundException(
                    'Provided workspace has unknown type!'
                );
            }
            if (str_contains($path, $userWorkspace->getPath())) {
                $workspaces[] = $userWorkspace;
            }
        }

        return $workspaces;
    }

    public function getDeepestWorkspace(array $workspaces): WorkspaceInterface
    {
        usort($workspaces, static function (WorkspaceInterface $a, WorkspaceInterface $b) {
            return strcmp($b->getPath(), $a->getPath());
        });

        return $workspaces[0];
    }

    /**
     * @throws WorkspaceNotFoundException
     */
    private function getWorkspacesObject(
        array $userWorkspaces
    ): array {
        $workspaces = [];
        foreach ($userWorkspaces as $userWorkspace) {
            $workspaces[] = match (true) {
                $userWorkspace instanceof Workspace\Asset => new AssetWorkspace(
                    $userWorkspace
                ),
                $userWorkspace instanceof Workspace\DataObject => new DataObjectWorkspace(
                    $userWorkspace
                ),
                $userWorkspace instanceof Workspace\Document => new DocumentWorkspace(
                    $userWorkspace
                ),
                default => throw new WorkspaceNotFoundException(
                    'Provided workspace has unknown type!'
                )
            };
        }

        return $workspaces;
    }
}
