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
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Document\DocumentSearchServiceInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\User;

final class ElementPermissionService implements ElementPermissionServiceInterface
{
    public function __construct(
        private readonly AssetSearchServiceInterface $assetSearchService,
        private readonly DataObjectSearchServiceInterface $dataObjectSearchService,
        private readonly DocumentSearchServiceInterface $documentSearchService,
        private readonly PermissionServiceInterface $permissionService
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
            $element instanceof Document => $this->isDocumentAllowed($element, $permission, $user),
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

    /**
     * @throws Exception
     */
    private function isDocumentAllowed(
        Document $document,
        string $permission,
        User $user
    ): bool {
        $documentResult = $this->documentSearchService->byId($document->getId(), $user);
        if (!$documentResult) {
            return false;
        }

        $permissions = $this->permissionService->getDocumentPermissions(
            $documentResult,
            $user
        );

        return $this->permissionService->getPermissionValue($permissions, $permission);
    }
}
