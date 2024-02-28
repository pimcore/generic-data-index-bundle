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

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\BasePermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermission;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DocumentPermission;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\WorkspaceInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
interface PermissionServiceInterface
{
    /**
     * @throws Exception
     */
    public function getAssetPermissions(string $assetPath, ?User $user): AssetPermissions;

    /**
     * @throws Exception
     */
    public function getDocumentPermissions(string $assetPath, ?User $user): DocumentPermission;

    /**
     * @throws Exception
     */
    public function getDataObjectPermissions(string $assetPath, ?User $user): DataObjectPermission;

    public function checkWorkspacePermission(
        WorkspaceInterface $workspace,
        string $permission
    ): bool;

    public function getPermissionValue(BasePermissions $permissions, string $permission): bool;
}
