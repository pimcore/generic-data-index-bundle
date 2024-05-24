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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\UserPermissionException;
use Pimcore\Model\User;

final class UserPermissionService implements UserPermissionServiceInterface
{
    public function canSearch(
        User $user,
        string $userPermission
    ): void {
        if (!$this->hasPermission($user, $userPermission)) {
            throw new UserPermissionException('User does not have permission to view assets');
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
