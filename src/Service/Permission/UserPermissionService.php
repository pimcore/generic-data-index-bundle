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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\UserPermissionException;
use Pimcore\Model\User;

final class UserPermissionService implements UserPermissionServiceInterface
{
    public function canSearch(
        User $user,
        string $userPermission
    ): void {
        if (!$user->isAdmin() && !$this->hasPermission($user, $userPermission)) {
            throw new UserPermissionException('User does not have permission to view assets');
        }
    }

    public function hasPermission(User $user, string $permission): bool
    {
        $permissions = $user->getPermissions();
        if (in_array($permission, $permissions)) {
            return true;
        }

        return false;
    }
}
