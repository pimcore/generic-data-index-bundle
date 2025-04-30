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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Permission;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\UserPermissionException;
use Pimcore\Model\User;

final class UserPermissionService implements UserPermissionServiceInterface
{
    public function canSearch(
        User $user,
        string $userPermission
    ): void {
        if (!$this->hasPermission($user, $userPermission)) {
            throw new UserPermissionException('User does not have permission to view ' . $userPermission);
        }
    }

    public function hasPermission(User $user, string $permission): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        $permissions = $user->getPermissions();
        if (in_array($permission, $permissions)) {
            return true;
        }

        return false;
    }
}
