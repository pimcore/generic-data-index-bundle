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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Permission;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\UserPermissionException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\ElementPermissionService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\ElementPermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\UserPermissionService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
final class ElementPermissionServiceTest extends Unit
{
    private User $user;

    private Asset $asset;

    private AssetSearchResultItem $assetSearchResultItem;

    public function _before(): void
    {
        $this->user = new User();
        $asset = new Asset();
        $asset->setId(1);
        $this->asset = $asset;
        $this->assetSearchResultItem = new AssetSearchResultItem();
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

    private function getElementPermissionService(bool $permissionValue = true): ElementPermissionService
    {
        return new ElementPermissionService(
            $this->makeEmpty(AssetSearchServiceInterface::class, [
                'byId' => $this->assetSearchResultItem
            ]),
            $this->makeEmpty(PermissionServiceInterface::class, [
                'getAssetPermissions' => new AssetPermissions(),
                'getPermissionValue' => $permissionValue
            ])
        );
    }
}