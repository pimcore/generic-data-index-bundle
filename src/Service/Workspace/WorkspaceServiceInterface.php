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

    public function mergeWorkspacesByPath(array $workspaces): array;

    public function getDeepestWorkspace(array $workspaces): WorkspaceInterface;
}
