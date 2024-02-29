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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Event\Asset;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Asset\PermissionEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResultItem;
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
