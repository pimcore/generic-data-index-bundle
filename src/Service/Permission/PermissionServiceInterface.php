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

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\BasePermissions;
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

    public function getPermissionValue(BasePermissions $permissions, string $permission): bool;
}
