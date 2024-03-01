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

use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\User;

final class ElementPermissionService implements ElementPermissionServiceInterface
{
    public function __construct(
        private readonly AssetSearchServiceInterface $assetSearchService,
        private readonly DataObjectSearchServiceInterface $dataObjectSearchService,
        private readonly PermissionServiceInterface $permissionService
    ) {
    }

    public function isAllowed(
        string $permission,
        ElementInterface $element,
        User $user
    ): bool {
        return match (true) {
            $element instanceof Asset => $this->isAssetAllowed($permission, $element, $user),
            $element instanceof DataObject => $this->isDataObjectAllowed($element, $permission, $user),
            default => false,
        };
    }

    private function isAssetAllowed(
        string $permission,
        Asset $asset,
        User $user
    ): bool {
        $assetResult = $this->assetSearchService->byId($asset->getId(), $user);
        if (!$assetResult) {
            return false;
        }

        $assetPermissions = $this->permissionService->getAssetPermissions(
            $assetResult,
            $user
        );

        return $this->permissionService->getPermissionValue($assetPermissions, $permission);
    }

    private function isDataObjectAllowed(
        DataObject $dataObject,
        string $permission,
        User $user
    ): bool {
        $dataObjectResult = $this->dataObjectSearchService->byId($dataObject->getId(), $user);
        if (!$dataObjectResult) {
            return false;
        }

        $permissions = $this->permissionService->getDataObjectPermissions(
            $dataObjectResult,
            $user
        );

        return $this->permissionService->getPermissionValue($permissions, $permission);
    }
}
