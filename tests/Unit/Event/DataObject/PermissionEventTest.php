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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Event\DataObject;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject\PermissionEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermission;

/**
 * @internal
 */
final class PermissionEventTest extends Unit
{
    public function testGetPermissionsMapping(): void
    {
        $permissions = new DataObjectPermission();
        $permissions->setView(false);
        $permissions->setPublish(false);
        $event = new PermissionEvent(new DataObjectSearchResultItem(), $permissions);

        $this->assertSame($permissions, $event->getPermissions());
        $this->assertFalse($event->getPermissions()->isView());
        $this->assertFalse($event->getPermissions()->isPublish());
    }

    public function testSetPermissionsMapping(): void
    {
        $permissions = new DataObjectPermission();
        $event = new PermissionEvent(new DataObjectSearchResultItem(), $permissions);
        $permissions->setView(false);
        $permissions->setPublish(false);
        $event->setPermissions($permissions);

        $this->assertSame($permissions, $event->getPermissions());
        $this->assertFalse($event->getPermissions()->isView());
        $this->assertFalse($event->getPermissions()->isPublish());
    }
}
