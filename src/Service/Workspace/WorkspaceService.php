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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\WorkspaceNotFoundException;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\AssetWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DataObjectWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DocumentWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\WorkspaceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\User\UserResolverInterface;
use Pimcore\Model\User;
use Pimcore\Model\User\Workspace;

/**
 * @internal
 */
final readonly class WorkspaceService implements WorkspaceServiceInterface
{
    public function __construct(
        private UserResolverInterface $userResolver
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
        $roleWorkspaces = $this->userResolver->getUserRoleById($roleId)?->$getter() ?? [];
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
