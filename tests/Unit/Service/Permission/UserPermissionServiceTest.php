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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Permission;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\UserPermissionException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\UserPermissionService;
use Pimcore\Model\User;

/**
 * @internal
 */
final class UserPermissionServiceTest extends Unit
{
    private User $user;

    public function _before(): void
    {
        $this->user = new User();
    }

    public function testCanSearchWithoutPermission(): void
    {
        $this->user->setAdmin(false);
        $this->user->setPermissions(['permission1', 'permission2']);

        $userPermissionService = new UserPermissionService();
        $this->expectException(UserPermissionException::class);
        $userPermissionService->canSearch($this->user, 'permission3');
    }

    public function testHasPermission(): void
    {
        $this->user->setPermissions(['permission1', 'permission2']);

        $userPermissionService = new UserPermissionService();
        $this->assertTrue($userPermissionService->hasPermission($this->user, 'permission1'));
        $this->assertFalse($userPermissionService->hasPermission($this->user, 'permission3'));
    }
}