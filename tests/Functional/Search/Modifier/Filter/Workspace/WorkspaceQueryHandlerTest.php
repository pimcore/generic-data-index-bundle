<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\Filter\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Db;
use Pimcore\Model\Asset\Folder;
use Pimcore\Model\Asset\Service;
use Pimcore\Model\User;
use Pimcore\Tests\Support\Util\TestHelper;

class WorkspaceQueryHandlerTest extends \Codeception\Test\Unit
{
    /**
     * @var \Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester
     */
    protected $tester;

    protected function _before()
    {
        $this->tester->enableSynchronousProcessing();
    }

    protected function _after()
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    // tests

    public function testAdmin()
    {
        $this->createTestAssetFolders();
        $this->assertSearchResultFolders([
            '/',
            '/test-folder-1',
            '/test-folder-1/sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
            '/test-folder-2',
            '/test-folder-2/sub-folder-2',
            '/test-folder-2/sub-folder-2/sub-sub-folder-2',
            '/test-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/test-folder-3',
            '/test-folder-3/sub-folder-3',
            '/test-folder-3/sub-folder-3/sub-sub-folder-3',
            '/test-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ], User::getByName('admin'));
    }

    public function testIncludeFolders(): void
    {
        $this->createTestAssetFolders();

        $user = $this->createUserWithAssetWorkspaces([
            '/test-folder-1' => true,
            '/test-folder-2' => true,
        ]);
        $this->assertSearchResultFolders([
            '/',
            '/test-folder-1',
            '/test-folder-1/sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
            '/test-folder-2',
            '/test-folder-2/sub-folder-2',
            '/test-folder-2/sub-folder-2/sub-sub-folder-2',
            '/test-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/test-folder-1' => true,
            '/test-folder-1/sub-folder-1' => true,
        ]);
        $this->assertSearchResultFolders([
            '/',
            '/test-folder-1',
            '/test-folder-1/sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1' => true,
        ]);
        $this->assertSearchResultFolders([
            '/',
            '/test-folder-1',
            '/test-folder-1/sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
        ], $user);

    }

    public function testExcludeFolders(): void
    {
        $this->createTestAssetFolders();

        $user = $this->createUserWithAssetWorkspaces([
            '/' => true,
            '/test-folder-2' => false,
        ]);
        $this->assertSearchResultFolders([
            '/',
            '/test-folder-1',
            '/test-folder-1/sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
            '/test-folder-3',
            '/test-folder-3/sub-folder-3',
            '/test-folder-3/sub-folder-3/sub-sub-folder-3',
            '/test-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/' => true,
            '/test-folder-1' => false,
            '/test-folder-1/sub-folder-1' => false,
        ]);
        $this->assertSearchResultFolders([
            '/',
            '/test-folder-2',
            '/test-folder-2/sub-folder-2',
            '/test-folder-2/sub-folder-2/sub-sub-folder-2',
            '/test-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/test-folder-3',
            '/test-folder-3/sub-folder-3',
            '/test-folder-3/sub-folder-3/sub-sub-folder-3',
            '/test-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/' => true,
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3' => false,
        ]);
        $this->assertSearchResultFolders([
            '/',
            '/test-folder-1',
            '/test-folder-1/sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-folder-2',
            '/test-folder-2/sub-folder-2',
            '/test-folder-2/sub-folder-2/sub-sub-folder-2',
            '/test-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/test-folder-3',
            '/test-folder-3/sub-folder-3',
            '/test-folder-3/sub-folder-3/sub-sub-folder-3',
            '/test-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ], $user);
    }

    public function testCombineIncludeExclude(): void
    {
        $this->createTestAssetFolders();

        $user = $this->createUserWithAssetWorkspaces([
            '/test-folder-1' => true,
            '/test-folder-1/sub-folder-1' => false,
        ]);
        $this->assertSearchResultFolders([
            '/',
            '/test-folder-1',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/test-folder-1' => true,
            '/test-folder-1/sub-folder-1/sub-sub-folder-1' => false,
        ]);
        $this->assertSearchResultFolders([
            '/',
            '/test-folder-1',
            '/test-folder-1/sub-folder-1',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/test-folder-1' => true,
            '/test-folder-1/sub-folder-1' => false,
            '/test-folder-1/sub-folder-1/sub-sub-folder-1' => true,
        ]);
        $this->assertSearchResultFolders([
            '/',
            '/test-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/test-folder-1' => true,
            '/test-folder-1/sub-folder-1' => false,
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1' => true,
        ]);
        $this->assertSearchResultFolders([
            '/',
            '/test-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/test-folder-1' => true,
            '/test-folder-1/sub-folder-1' => false,
            '/test-folder-1/sub-folder-1/sub-sub-folder-1' => true,
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1' => false,
        ]);
        $this->assertSearchResultFolders([
            '/',
            '/test-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/' => true,
            '/test-folder-1' => false,
            '/test-folder-1/sub-folder-1' => true,
            '/test-folder-1/sub-folder-1/sub-sub-folder-1' => false,
            '/test-folder-2' => false,
            '/test-folder-3' => false,
        ]);
        $this->assertSearchResultFolders([
            '/',
            '/test-folder-1/sub-folder-1',
        ], $user);
        $user = $this->createUserWithAssetWorkspaces([
            '/' => true,
            '/test-folder-1' => false,
            '/test-folder-1/sub-folder-1/sub-sub-folder-1' => true,
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2' => false,
            '/test-folder-2' => false,
            '/test-folder-3' => false,
        ]);
        $this->assertSearchResultFolders([
            '/',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
        ], $user);

    }

    private function assertSearchResultFolders(array $expectedPaths, User $user)
    {
        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->setUser($user)
        ;
        $searchResult = $searchService->search($assetSearch);

        $paths = array_map(function (AssetSearchResultItem $item) {
            return $item->getPath() . $item->getKey();
        }, $searchResult->getItems());

        sort($expectedPaths);
        sort($paths);

        $this->assertEquals($expectedPaths, $paths);
    }

    private function createUserWithAssetWorkspaces(array $workspaces): User
    {
        $user = new User();
        $user
            ->setPermission('assets', true)
            ->setUsername('test-user-' . uniqid())
            ->save();

        $workspaceArray = [];
        foreach ($workspaces as $workspace => $permission) {

            $workspaceObject = (new User\Workspace\Asset())
                ->setList($permission)
                ->setCpath($workspace)
                ->setCid(Db::get()->fetchOne('select id from assets where concat(path, filename) = ?', [$workspace]))
                ->setUserId($user->getId());

            $workspaceObject->save();
            $workspaceArray[] = $workspaceObject;
        }
        $user->setWorkspacesAsset($workspaceArray);

        return $user;
    }

    private function createTestAssetFolders(): void
    {
        $folder = Folder::getById(1);
        $folder->save();

        $folders = [
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
            '/test-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/test-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ];

        foreach ($folders as $folder) {
            Service::createFolderByPath($folder);
        }
    }
}
