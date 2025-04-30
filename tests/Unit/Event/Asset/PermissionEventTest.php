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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Event\Asset;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\PermissionEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;

/**
 * @internal
 */
final class PermissionEventTest extends Unit
{
    public function testGetPermissionsMapping(): void
    {
        $permissions = new AssetPermissions();
        $permissions->setView(false);
        $event = new PermissionEvent(new AssetSearchResultItem(), $permissions);

        $this->assertSame($permissions, $event->getPermissions());
        $this->assertFalse($event->getPermissions()->isView());
    }

    public function testSetPermissionsMapping(): void
    {
        $permissions = new AssetPermissions();
        $event = new PermissionEvent(new AssetSearchResultItem(), $permissions);
        $permissions->setView(false);
        $permissions->setRename(false);
        $event->setPermissions($permissions);

        $this->assertSame($permissions, $event->getPermissions());
        $this->assertFalse($event->getPermissions()->isView());
        $this->assertFalse($event->getPermissions()->isRename());
    }
}
