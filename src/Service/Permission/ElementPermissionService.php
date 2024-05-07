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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\AssetSearchException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DataObjectSearchException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DocumentSearchException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Document\DocumentSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
final class ElementPermissionService implements ElementPermissionServiceInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly AssetSearchServiceInterface $assetSearchService,
        private readonly DataObjectSearchServiceInterface $dataObjectSearchService,
        private readonly DocumentSearchServiceInterface $documentSearchService,
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
            $element instanceof Document => $this->isDocumentAllowed($element, $permission, $user),
            default => false,
        };
    }

    private function isAssetAllowed(
        string $permission,
        Asset $asset,
        User $user
    ): bool {
        try {
            $assetResult = $this->assetSearchService->byId($asset->getId(), $user);
        } catch (AssetSearchException $e) {
            $this->logger->error('Asset search failed in the element permission check: ' . $e->getMessage());

            return false;
        }

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
        try {
            $dataObjectResult = $this->dataObjectSearchService->byId($dataObject->getId(), $user);
        } catch (DataObjectSearchException $e) {
            $this->logger->error(
                'Data Object search failed in the element permission check: ' . $e->getMessage()
            );

            return false;
        }

        if (!$dataObjectResult) {
            return false;
        }

        $permissions = $this->permissionService->getDataObjectPermissions(
            $dataObjectResult,
            $user
        );

        return $this->permissionService->getPermissionValue($permissions, $permission);
    }

    private function isDocumentAllowed(
        Document $document,
        string $permission,
        User $user
    ): bool {
        try {
            $documentResult = $this->documentSearchService->byId($document->getId(), $user);
        } catch (DocumentSearchException $e) {
            $this->logger->error(
                'Document search failed in the element permission check: ' . $e->getMessage()
            );

            return false;
        }

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
