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

namespace Pimcore\Bundle\GenericDataIndexBundle\Event\DataObject;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermission;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires when element permissions are being retrieved.
 * Can be used to manipulate the user permissions for element actions.
 * You will find a description and example on how it works in the docs.
 */
final class PermissionEvent extends Event
{
    public function __construct(
        private readonly DataObjectSearchResultItem $searchResultItem,
        private DataObjectPermission $permissions
    ) {
    }

    public function getElement(): DataObjectSearchResultItem
    {
        return $this->searchResultItem;
    }

    public function getPermissions(): DataObjectPermission
    {
        return $this->permissions;
    }

    public function setPermissions(DataObjectPermission $permission): void
    {
        $this->permissions = $permission;
    }
}
