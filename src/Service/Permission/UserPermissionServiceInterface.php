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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Permission;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
interface UserPermissionServiceInterface
{
    public function canSearch(
        SearchInterface $search,
        string $userPermission,
        string $workspaceType
    ): SearchInterface;

    public function hasPermission(User $user, string $permission): bool;

    public function isAllowed(
        string $permission,
        ElementInterface $element,
        User $user
    ): bool;
}
