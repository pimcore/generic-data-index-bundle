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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\Filter\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\ElementSearchResultItemInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Element\ElementSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Db;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
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

    public function testHandleWorkspaceQueryAdmin()
    {
        $this->createTestAssetFolders();
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
            '/test-asset-folder-2',
            '/test-asset-folder-2/sub-folder-2',
            '/test-asset-folder-2/sub-folder-2/sub-sub-folder-2',
            '/test-asset-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/test-asset-folder-3',
            '/test-asset-folder-3/sub-folder-3',
            '/test-asset-folder-3/sub-folder-3/sub-sub-folder-3',
            '/test-asset-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ], User::getByName('admin'));
    }

    public function testHandleWorkspaceQueryIncludeFolders(): void
    {
        $this->createTestAssetFolders();

        $user = $this->createUserWithAssetWorkspaces([
            '/test-asset-folder-1' => true,
            '/test-asset-folder-2' => true,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
            '/test-asset-folder-2',
            '/test-asset-folder-2/sub-folder-2',
            '/test-asset-folder-2/sub-folder-2/sub-sub-folder-2',
            '/test-asset-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/test-asset-folder-1' => true,
            '/test-asset-folder-1/sub-folder-1' => true,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1' => true,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
        ], $user);

    }

    public function testHandleWorkspaceQueryExcludeFolders(): void
    {
        $this->createTestAssetFolders();

        $user = $this->createUserWithAssetWorkspaces([
            '/' => true,
            '/test-asset-folder-2' => false,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
            '/test-asset-folder-3',
            '/test-asset-folder-3/sub-folder-3',
            '/test-asset-folder-3/sub-folder-3/sub-sub-folder-3',
            '/test-asset-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/' => true,
            '/test-asset-folder-1' => false,
            '/test-asset-folder-1/sub-folder-1' => false,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-2',
            '/test-asset-folder-2/sub-folder-2',
            '/test-asset-folder-2/sub-folder-2/sub-sub-folder-2',
            '/test-asset-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/test-asset-folder-3',
            '/test-asset-folder-3/sub-folder-3',
            '/test-asset-folder-3/sub-folder-3/sub-sub-folder-3',
            '/test-asset-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/' => true,
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3' => false,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-asset-folder-2',
            '/test-asset-folder-2/sub-folder-2',
            '/test-asset-folder-2/sub-folder-2/sub-sub-folder-2',
            '/test-asset-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/test-asset-folder-3',
            '/test-asset-folder-3/sub-folder-3',
            '/test-asset-folder-3/sub-folder-3/sub-sub-folder-3',
            '/test-asset-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ], $user);
    }

    public function testHandleWorkspaceQueryCombineIncludeExclude(): void
    {
        $this->createTestAssetFolders();

        $user = $this->createUserWithAssetWorkspaces([
            '/test-asset-folder-1' => true,
            '/test-asset-folder-1/sub-folder-1' => false,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/test-asset-folder-1' => true,
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1' => false,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/test-asset-folder-1' => true,
            '/test-asset-folder-1/sub-folder-1' => false,
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1' => true,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/test-asset-folder-1' => true,
            '/test-asset-folder-1/sub-folder-1' => false,
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1' => true,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/test-asset-folder-1' => true,
            '/test-asset-folder-1/sub-folder-1' => false,
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1' => true,
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1' => false,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/' => true,
            '/test-asset-folder-1' => false,
            '/test-asset-folder-1/sub-folder-1' => true,
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1' => false,
            '/test-asset-folder-2' => false,
            '/test-asset-folder-3' => false,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1/sub-folder-1',
        ], $user);
        $user = $this->createUserWithAssetWorkspaces([
            '/' => true,
            '/test-asset-folder-1' => false,
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1' => true,
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2' => false,
            '/test-asset-folder-2' => false,
            '/test-asset-folder-3' => false,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
        ], $user);

    }

    public function testHandleElementWorkspacesQuery(): void
    {
        $this->createTestAssetFolders();
        $this->createTestDataObjectFolders();
        $this->createTestDocumentFolders();

        $user = $this->createUserWithWorkspaces(
            [
                '/test-asset-folder-1' => true,
                '/test-asset-folder-2' => true,
            ],
            [
                '/test-document-folder-2' => true,
            ],
            [
                '/test-object-folder-3' => true,
            ]
        );

        $this->assertElementSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
            '/test-asset-folder-2',
            '/test-asset-folder-2/sub-folder-2',
            '/test-asset-folder-2/sub-folder-2/sub-sub-folder-2',
            '/test-asset-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/',
            '/test-document-folder-2',
            '/test-document-folder-2/sub-folder-2',
            '/test-document-folder-2/sub-folder-2/sub-sub-folder-2',
            '/test-document-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/',
            '/test-object-folder-3',
            '/test-object-folder-3/sub-folder-3',
            '/test-object-folder-3/sub-folder-3/sub-sub-folder-3',
            '/test-object-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ], $user);


        $user = $this->createUserWithWorkspaces(
            [
                '/test-asset-folder-1' => true,
                '/test-asset-folder-2' => true,
            ],
            [
                '/test-document-folder-2' => true,
            ],
            [
                '/test-object-folder-3' => true,
            ]
        );

        $user->setPermission('assets', false)->save();

        $this->assertElementSearchResultFolders([
            '/',
            '/test-document-folder-2',
            '/test-document-folder-2/sub-folder-2',
            '/test-document-folder-2/sub-folder-2/sub-sub-folder-2',
            '/test-document-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/',
            '/test-object-folder-3',
            '/test-object-folder-3/sub-folder-3',
            '/test-object-folder-3/sub-folder-3/sub-sub-folder-3',
            '/test-object-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ], $user);

        $user->setPermission('documents', false)->save();

        $this->assertElementSearchResultFolders([
            '/',
            '/test-object-folder-3',
            '/test-object-folder-3/sub-folder-3',
            '/test-object-folder-3/sub-folder-3/sub-sub-folder-3',
            '/test-object-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ], $user);

        $user->setPermission('objects', false)->save();

        $this->assertElementSearchResultFolders([], $user);

    }

    private function assertAssetSearchResultFolders(array $expectedPaths, User $user)
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

    private function assertElementSearchResultFolders(array $expectedPaths, User $user)
    {
        /** @var ElementSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.element-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $elementSearch = $searchProvider
            ->createElementSearch()
            ->setUser($user)
        ;
        $searchResult = $searchService->search($elementSearch);

        $paths = array_map(function (ElementSearchResultItemInterface $item) {
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

    private function createUserWithWorkspaces(array $assetWorkspaces, array $documentWorkspaces, array $objectWorkspaces): User
    {
        $user = new User();
        $user
            ->setPermission('assets', true)
            ->setPermission('documents', true)
            ->setPermission('objects', true)
            ->setUsername('test-user-' . uniqid())
            ->save();

        $workspaceArray = [];
        foreach ($assetWorkspaces as $workspace => $permission) {

            $workspaceObject = (new User\Workspace\Asset())
                ->setList($permission)
                ->setCpath($workspace)
                ->setCid(Db::get()->fetchOne('select id from assets where concat(path, filename) = ?', [$workspace]))
                ->setUserId($user->getId());

            $workspaceObject->save();
            $workspaceArray[] = $workspaceObject;
        }
        $user->setWorkspacesAsset($workspaceArray);

        $workspaceArray = [];
        foreach ($documentWorkspaces as $workspace => $permission) {

            $workspaceObject = (new User\Workspace\Document())
                ->setList($permission)
                ->setCpath($workspace)
                ->setCid(Db::get()->fetchOne('select id from documents where concat(path, `key`) = ?', [$workspace]))
                ->setUserId($user->getId());

            $workspaceObject->save();
            $workspaceArray[] = $workspaceObject;
        }
        $user->setWorkspacesDocument($workspaceArray);

        $workspaceArray = [];
        foreach ($objectWorkspaces as $workspace => $permission) {

            $workspaceObject = (new User\Workspace\DataObject())
                ->setList($permission)
                ->setCpath($workspace)
                ->setCid(Db::get()->fetchOne('select id from objects where concat(path, `key`) = ?', [$workspace]))
                ->setUserId($user->getId());

            $workspaceObject->save();
            $workspaceArray[] = $workspaceObject;
        }
        $user->setWorkspacesObject($workspaceArray);

        return $user;
    }

    private function createTestAssetFolders(): void
    {
        $folder = Asset\Folder::getByPath('/');
        $folder?->save();

        $folders = [
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
            '/test-asset-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/test-asset-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ];

        foreach ($folders as $folder) {
            Asset\Service::createFolderByPath($folder);
        }
    }

    private function createTestDataObjectFolders(): void
    {
        $folder = DataObject\Folder::getByPath('/');
        $folder?->save();

        $folders = [
            '/test-object-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-object-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/test-object-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ];

        foreach ($folders as $folder) {
            DataObject\Service::createFolderByPath($folder);
        }
    }

    private function createTestDocumentFolders(): void
    {
        $folder = Document\Folder::getByPath('/');
        $folder?->save();

        $folders = [
            '/test-document-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-document-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/test-document-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ];

        foreach ($folders as $folder) {
            Document\Service::createFolderByPath($folder);
        }
    }
}
