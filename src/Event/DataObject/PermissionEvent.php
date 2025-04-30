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
