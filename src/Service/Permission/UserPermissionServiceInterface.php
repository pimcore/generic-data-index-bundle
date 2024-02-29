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

use Pimcore\Model\User;

/**
 * @internal
 */
interface UserPermissionServiceInterface
{
    public function canSearch(
        User $user,
        string $userPermission
    ): void;

    public function hasPermission(User $user, string $permission): bool;
}
