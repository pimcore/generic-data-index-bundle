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
use Pimcore\Bundle\GenericDataIndexBundle\Permission\AssetPermissions;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\ElementPermissionService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Model\Asset;
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
                'byId' => $this->assetSearchResultItem,
            ]),
            $this->makeEmpty(DataObjectSearchServiceInterface::class, [
                'byId' => $this->assetSearchResultItem,
            ]),
            $this->makeEmpty(PermissionServiceInterface::class, [
                'getAssetPermissions' => new AssetPermissions(),
                'getPermissionValue' => $permissionValue,
            ])
        );
    }
}
