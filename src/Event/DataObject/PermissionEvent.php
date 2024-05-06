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

namespace Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermissions;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires when element permissions are being retrieved.
 * Can be used to manipulate the user permissions for element actions.
 * You will find a description and example on how it works in the docs.
 */
final class PermissionEvent extends Event
{
    public function __construct(
        private readonly SearchResult\DataObjectSearchResultItem $searchResultItem,
        private DataObjectPermissions $permissions
    ) {
    }

    public function getElement(): SearchResult\DataObjectSearchResultItem
    {
        return $this->searchResultItem;
    }

    public function getPermissions(): DataObjectPermissions
    {
        return $this->permissions;
    }

    public function setPermissions(DataObjectPermissions $permission): void
    {
        $this->permissions = $permission;
    }
}
