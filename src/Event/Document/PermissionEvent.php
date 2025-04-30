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

namespace Pimcore\Bundle\GenericDataIndexBundle\Event\Document;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DocumentPermissions;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires when element permissions are being retrieved.
 * Can be used to manipulate the user permissions for element actions.
 * You will find a description and example on how it works in the docs.
 */
final class PermissionEvent extends Event
{
    public function __construct(
        private readonly SearchResult\DocumentSearchResultItem $searchResultItem,
        private DocumentPermissions $permissions
    ) {
    }

    public function getElement(): SearchResult\DocumentSearchResultItem
    {
        return $this->searchResultItem;
    }

    public function getPermissions(): DocumentPermissions
    {
        return $this->permissions;
    }

    public function setPermissions(DocumentPermissions $permission): void
    {
        $this->permissions = $permission;
    }
}
