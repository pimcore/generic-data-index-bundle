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
