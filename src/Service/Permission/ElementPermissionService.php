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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Permission;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\User;

final readonly class ElementPermissionService implements ElementPermissionServiceInterface
{
    public function __construct(
        private AssetSearchServiceInterface $assetSearchService,
        private DataObjectSearchServiceInterface $dataObjectSearchService,
        private PermissionServiceInterface $permissionService
    ) {
    }

    /**
     * @throws Exception
     */
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

    /**
     * @throws Exception
     */
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

    /**
     * @throws Exception
     */
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
