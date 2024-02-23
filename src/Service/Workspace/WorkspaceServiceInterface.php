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

use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\WorkspaceInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
interface WorkspaceServiceInterface
{
    public function getUserWorkspaces(
        string $workspaceType,
        ?User $user
    ): array;

    public function getUserRoleWorkspaces(
        User $user,
        string $workspaceType,
        string $path
    ): array;

    public function getRoleWorkspaces(string $type, int $roleId): array;

    public function getRelevantWorkspaces(
        array $userWorkspaces,
        string $path
    ): array;

    public function getDeepestWorkspace(array $workspaces): WorkspaceInterface;
}
