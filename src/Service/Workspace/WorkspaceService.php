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
use Pimcore\Model\User\Workspace;

/**
 * @internal
 */
final class WorkspaceService implements WorkspaceServiceInterface
{
    public function __construct(
        private readonly UserResolverInterface $userResolver
    )
    {
    }

    /**
     * @throws WorkspaceNotFoundException
     */
    public function getRelevantWorkspaces(
        array $userWorkspaces,
        string $assetPath
    ): array
    {
        $workspaces = [];
        foreach ($userWorkspaces as $userWorkspace) {
            if (!str_contains($assetPath, $userWorkspace->getCpath())) {
                continue;
            }

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

    public function getRoleWorkspaces(array $roleIds, string $type): array
    {
        $workspaces = [];
        $getter = 'getWorkspaces' . ucfirst($type);
        foreach ($roleIds as $roleId) {
            $roleWorkspaces = $this->userResolver->getUserRoleById($roleId)?->$getter();
            foreach ($roleWorkspaces as $roleWorkspace) {
                $workspaces[] = $roleWorkspace;
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
}