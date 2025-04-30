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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Event\Document;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Event\Document\PermissionEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DocumentPermissions;

/**
 * @internal
 */
final class PermissionEventTest extends Unit
{
    public function testGetPermissionsMapping(): void
    {
        $permissions = new DocumentPermissions();
        $permissions->setView(false);
        $event = new PermissionEvent(new DocumentSearchResultItem(), $permissions);

        $this->assertSame($permissions, $event->getPermissions());
        $this->assertFalse($event->getPermissions()->isView());
    }

    public function testSetPermissionsMapping(): void
    {
        $permissions = new DocumentPermissions();
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
