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

use Pimcore\Bundle\GenericDataIndexBundle\Service\Transformer\SearchResultItem\AssetToSearchResultItemTransformerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Transformer\SearchResultItem\DataObjectToSearchResultItemTransformerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Transformer\SearchResultItem\DocumentToSearchResultItemTransformerInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
final readonly class ElementPermissionService implements ElementPermissionServiceInterface
{

    public function __construct(
        private AssetToSearchResultItemTransformerInterface $assetTransformer,
        private DataObjectToSearchResultItemTransformerInterface $dataObjectTransformer,
        private DocumentToSearchResultItemTransformerInterface $documentTransformer,
        private PermissionServiceInterface $permissionService
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
        $assetSearchResultItem = $this->assetTransformer->transform($asset, $user);

        $assetPermissions = $this->permissionService->getAssetPermissions(
            $assetSearchResultItem,
            $user
        );

        return $this->permissionService->getPermissionValue($assetPermissions, $permission);
    }

    private function isDataObjectAllowed(
        DataObject $dataObject,
        string $permission,
        User $user
    ): bool {
        $dataObjectSearchResultItem = $this->dataObjectTransformer->transform($dataObject, $user);

        $permissions = $this->permissionService->getDataObjectPermissions(
            $dataObjectSearchResultItem,
            $user
        );

        return $this->permissionService->getPermissionValue($permissions, $permission);
    }

    private function isDocumentAllowed(
        Document $document,
        string $permission,
        User $user
    ): bool {
        $documentSearchResultItem = $this->documentTransformer->transform($document, $user);

        $permissions = $this->permissionService->getDocumentPermissions(
            $documentSearchResultItem,
            $user
        );

        return $this->permissionService->getPermissionValue($permissions, $permission);
    }
}
