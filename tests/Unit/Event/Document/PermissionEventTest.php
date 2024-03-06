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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Event\Document;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Document\PermissionEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DocumentPermission;

/**
 * @internal
 */
final class PermissionEventTest extends Unit
{
    public function testGetPermissionsMapping(): void
    {
        $permissions = new DocumentPermission();
        $permissions->setView(false);
        $event = new PermissionEvent(new DocumentSearchResultItem(), $permissions);

        $this->assertSame($permissions, $event->getPermissions());
        $this->assertFalse($event->getPermissions()->isView());
    }

    public function testSetPermissionsMapping(): void
    {
        $permissions = new DocumentPermission();
        $event = new PermissionEvent(new DocumentSearchResultItem(), $permissions);
        $permissions->setView(false);
        $permissions->setRename(false);
        $permissions->setPublish(false);
        $event->setPermissions($permissions);

        $this->assertSame($permissions, $event->getPermissions());
        $this->assertFalse($event->getPermissions()->isView());
        $this->assertFalse($event->getPermissions()->isRename());
        $this->assertFalse($event->getPermissions()->isPublish());
    }
}
