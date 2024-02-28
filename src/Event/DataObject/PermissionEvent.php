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

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fires after element search.
 * Can be used to manipulate the user permissions.
 * You will find a description and example on how it works in the docs.
 */
final class PermissionEvent extends Event
{
    public function __construct(
        private readonly mixed $searchResultItem,
        private string $permission
    ) {
    }

    public function getElement(): mixed
    {
        // ToDo change search result item type
        return $this->searchResultItem;
    }

    public function getPermission(): string
    {
        return $this->permission;
    }

    public function setPermission(string $permission): void
    {
        $this->permission = $permission;
    }
}
