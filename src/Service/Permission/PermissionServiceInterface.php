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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Permission;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DocumentPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\WorkspaceInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
interface PermissionServiceInterface
{
    public function getAssetPermissions(
        AssetSearchResultItem $asset,
        ?User $user
    ): AssetPermissions;

    public function getDocumentPermissions(
        DocumentSearchResultItem $document,
        ?User $user
    ): DocumentPermissions;

    public function getDataObjectPermissions(
        DataObjectSearchResultItem $object,
        ?User $user
    ): DataObjectPermissions;

    public function checkWorkspacePermission(
        WorkspaceInterface $workspace,
        string $permission
    ): bool;

    public function getPermissionValue(
        AssetPermissions|DocumentPermissions|DataObjectPermissions $permissions,
        string $permission,
        ?string $permissionValueKey = null
    ): bool;

    public function getSpecialPermissionValues(DataObjectPermissions $permissions, string $permission): array;
}
