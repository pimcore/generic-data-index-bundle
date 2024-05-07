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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Permission;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\DocumentPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\ElementPermissionService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Document\DocumentSearchServiceInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\User;

/**
 * @internal
 */
final class ElementPermissionServiceTest extends Unit
{
    private User $user;

    private Asset $asset;

    private DataObject $dataObject;

    private Document $document;

    private AssetSearchResultItem $assetSearchResultItem;

    private DataObjectSearchResultItem $dataObjectSearchResultItem;

    private DocumentSearchResultItem $documentSearchResultItem;

    public function _before(): void
    {
        $this->user = new User();
        $this->asset = $this->getAsset();
        $this->dataObject = $this->getDatObject();
        $this->document = $this->getDocument();
        $this->assetSearchResultItem = new AssetSearchResultItem();
        $this->documentSearchResultItem = new DocumentSearchResultItem();
        $this->dataObjectSearchResultItem = new DataObjectSearchResultItem();
    }

    public function testIsAllowedWithAsset(): void
    {
        $elementPermissionService = $this->getElementPermissionService();
        $this->assertTrue($elementPermissionService->isAllowed('read', $this->asset, $this->user));
    }

    public function testIsAllowedWithAssetAndNoPermission(): void
    {
        $elementPermissionService = $this->getElementPermissionService(false);
        $this->assertFalse($elementPermissionService->isAllowed('list', $this->asset, $this->user));
    }

    public function testIsAllowedWithDataObject(): void
    {
        $elementPermissionService = $this->getElementPermissionService();
        $this->assertTrue($elementPermissionService->isAllowed('read', $this->dataObject, $this->user));
    }

    public function testIsAllowedWithDataObjectAndNoPermission(): void
    {
        $elementPermissionService = $this->getElementPermissionService(false);
        $this->assertFalse($elementPermissionService->isAllowed('list', $this->dataObject, $this->user));
    }

    public function testIsAllowedWithDocument(): void
    {
        $elementPermissionService = $this->getElementPermissionService();
        $this->assertTrue($elementPermissionService->isAllowed('read', $this->document, $this->user));
    }

    public function testIsAllowedWithDocumentAndNoPermission(): void
    {
        $elementPermissionService = $this->getElementPermissionService(false);
        $this->assertFalse($elementPermissionService->isAllowed('publish', $this->document, $this->user));
    }

    private function getElementPermissionService(bool $permissionValue = true): ElementPermissionService
    {
        return new ElementPermissionService(
            $this->makeEmpty(AssetSearchServiceInterface::class, [
                'byId' => $this->assetSearchResultItem,
            ]),
            $this->makeEmpty(DataObjectSearchServiceInterface::class, [
                'byId' => $this->dataObjectSearchResultItem,
            ]),
            $this->makeEmpty(DocumentSearchServiceInterface::class, [
                'byId' => $this->documentSearchResultItem,
            ]),
            $this->makeEmpty(PermissionServiceInterface::class, [
                'getAssetPermissions' => new AssetPermissions(),
                'getDataObjectPermissions' => new DataObjectPermissions(),
                'getDocumentPermissions' => new DocumentPermissions(),
                'getPermissionValue' => $permissionValue,
            ])
        );
    }

    private function getAsset(): Asset
    {

        $asset = new Asset();
        $asset->setId(1);

        return $asset;
    }

    private function getDatObject(): DataObject
    {
        $dataObject = new DataObject();
        $dataObject->setId(1);

        return $dataObject;
    }

    private function getDocument(): Document
    {
        $document = new Document();
        $document->setId(1);

        return $document;
    }
}
