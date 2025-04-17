<?php

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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Service;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\AssetWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Folder;
use Pimcore\Model\User;
use Pimcore\Tests\Support\Util\TestHelper;

class PermissionServiceTest extends Unit
{
    protected IndexTester $tester;

    private User $user;

    private Asset $asset1;

    private Asset $asset2;

    private Folder $folder1;

    private Folder $folder2;

    private PermissionServiceInterface $permissionService;

    private AssetSearchServiceInterface $assetSearchService;

    protected function _before(): void
    {
        $this->permissionService = $this->tester->grabService(
            PermissionServiceInterface::class
        );
        $this->assetSearchService = $this->tester->grabService(
            AssetSearchServiceInterface::class
        );
        $this->user = new User();
        $this->tester->enableSynchronousProcessing();
        $this->createAssets();
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    public function testBasicUserPermissions(): void
    {
        $this->user->setWorkspacesAsset(
            [
                $this->tester->getWorkspace(
                    path: '/',
                    permissions: ['list' => true],
                    type: AssetWorkspace::WORKSPACE_TYPE
                ),
            ]
        );

        $searchResultItem = $this->assetSearchService->byId($this->asset2->getId());
        $assetPermissions = $this->permissionService->getAssetPermissions($searchResultItem, $this->user);
        $this->assertTrue($assetPermissions->isList());
    }

    public function testBasicUserNestedPermissions(): void
    {
        $this->user->setWorkspacesAsset(
            [
                $this->tester->getWorkspace(
                    path: '/',
                    permissions: ['list' => true],
                    type: AssetWorkspace::WORKSPACE_TYPE
                ),
                $this->tester->getWorkspace(
                    path: $this->folder1->getFullPath(),
                    permissions: ['list' => false],
                    type: AssetWorkspace::WORKSPACE_TYPE
                ),
            ]
        );

        $resultAsset1 = $this->assetSearchService->byId($this->asset1->getId());
        $resultAsset2 = $this->assetSearchService->byId($this->asset2->getId());
        $rootPermissions = $this->permissionService->getAssetPermissions(
            (new AssetSearchResultItem())->setParentId(0)->setFullPath('/'),
            $this->user
        );
        $asset1Permissions = $this->permissionService->getAssetPermissions($resultAsset1, $this->user);
        $asset2Permissions = $this->permissionService->getAssetPermissions($resultAsset2, $this->user);

        $this->assertTrue($rootPermissions->isList());
        $this->assertFalse($asset1Permissions->isList());
        $this->assertFalse($asset2Permissions->isList());
    }

    public function testUserPermissionsCombineIncludeExclude(): void
    {
        $this->user->setWorkspacesAsset(
            [
                $this->tester->getWorkspace(
                    path: '/',
                    permissions: ['list' => true],
                    type: AssetWorkspace::WORKSPACE_TYPE
                ),
                $this->tester->getWorkspace(
                    path: $this->folder1->getFullPath(),
                    permissions: ['list' => false],
                    type: AssetWorkspace::WORKSPACE_TYPE
                ),
                $this->tester->getWorkspace(
                    path: $this->folder2->getFullPath(),
                    permissions: ['list' => true],
                    type: AssetWorkspace::WORKSPACE_TYPE
                ),
            ]
        );

        $resultAsset1 = $this->assetSearchService->byId($this->asset1->getId());
        $resultFolder1 = $this->assetSearchService->byId($this->folder1->getId());
        $resultFolder2 = $this->assetSearchService->byId($this->folder2->getId());
        $resultAsset2 = $this->assetSearchService->byId($this->asset2->getId());
        $rootPermissions = $this->permissionService->getAssetPermissions(
            (new AssetSearchResultItem())->setParentId(0)->setFullPath('/'),
            $this->user
        );
        $asset1Permissions = $this->permissionService->getAssetPermissions($resultAsset1, $this->user);
        $folder1Permissions = $this->permissionService->getAssetPermissions($resultFolder1, $this->user);
        $folder2Permissions = $this->permissionService->getAssetPermissions($resultFolder2, $this->user);
        $asset2Permissions = $this->permissionService->getAssetPermissions($resultAsset2, $this->user);

        $this->assertTrue($rootPermissions->isList());
        $this->assertFalse($folder1Permissions->isList());
        $this->assertFalse($asset1Permissions->isList());
        $this->assertTrue($folder2Permissions->isList());
        $this->assertTrue($asset2Permissions->isList());
    }

    private function createAssets(): void
    {
        // Path /test-folder/asset-in-folder
        // Path /test-folder/sub-folder/asset-in-sub-folder

        $folder1 = TestHelper::createAssetFolder();
        $folder2 = TestHelper::createAssetFolder();
        $asset1 = TestHelper::createImageAsset();
        $asset2 = TestHelper::createImageAsset();
        $folder1
            ->setKey('test-folder')
            ->setLocked('propagate')
            ->save();
        $asset1
            ->setParent($folder1)
            ->setKey('asset-in-folder')
            ->save();
        $folder2
            ->setParent($folder1)
            ->setKey('sub-folder')
            ->save();
        $asset2
            ->setParent($folder2)
            ->setKey('asset-in-sub-folder')
            ->save();

        $this->folder1 = $folder1;
        $this->folder2 = $folder2;
        $this->asset1 = $asset1;
        $this->asset2 = $asset2;
    }
}
