<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\Filter\Workspace;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\ElementSearchResultItemInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Document\DocumentSearchServiceInterface;
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
    private const ASSET_ID_BY_PATH_QUERY = 'select id from assets where concat(path, filename) = ?';

    private const DATA_OBJECT_ID_BY_PATH_QUERY = 'select id from objects where concat(path, `key`) = ?';

    private const DOCUMENT_ID_BY_PATH_QUERY = 'select id from documents where concat(path, `key`) = ?';

    /**
     * @var \Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester
     */
    protected $tester;

    protected function _before()
    {
        $this->tester->enableSynchronousProcessing();
    }

    /**
     * @var User\UserRole[]
     */
    private array $createdUsers = [];

    protected function _after()
    {
        try {
            foreach ($this->createdUsers as $createdUser) {
                $createdUser->delete();
            }
        } finally {
            $this->createdUsers = [];

            TestHelper::cleanUp();
            $this->tester->flushIndex();
            $this->tester->cleanupIndex();
            $this->tester->flushIndex();
        }
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
            '/test-asset-folder-1/sub-folder-1',
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
            '/test-asset-folder-1/sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
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
            '/test-asset-folder-1/sub-folder-1',
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
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/test-asset-folder-1' => true,
            '/test-asset-folder-1/sub-folder-1' => false,
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1' => false,
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1' => true,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/' => false,
            '/test-asset-folder-1' => false,
            '/test-asset-folder-1/sub-folder-1' => true,
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1' => false,
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1' => true,
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2' => false,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
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
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
        ], $user);

        $user = $this->createUserWithAssetWorkspaces([
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2' => true,
        ]);
        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
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

    public function testHandleWorkspaceQueryParentPathsOnlyForListPermission(): void
    {
        $this->createTestAssetFolders();

        $user = $this->createUserWithAssetWorkspaces(
            ['/test-asset-folder-1/sub-folder-1/sub-sub-folder-1' => true],
            true
        );

        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
        ], $user);

        $this->assertAssetSearchResultFolders([
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
        ], $user, PermissionTypes::VIEW);

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $this->assertByIdReturnsOnlyViewableItems(
            $searchService,
            fn () => array_map(
                fn (AssetSearchResultItem $item) => $item->getId(),
                $searchService->search(
                    $searchProvider->createAssetSearch()->setUser(User::getByName('admin'))
                )->getItems()
            ),
            Asset::getByPath('/test-asset-folder-1/sub-folder-1')->getId(),
            Asset::getByPath('/test-asset-folder-1/sub-folder-1/sub-sub-folder-1')->getId(),
            $user,
            $this->createUserWithAssetWorkspaces(['/test-asset-folder-1/sub-folder-1/sub-sub-folder-1' => true])
        );
    }

    public function testElementSearchViewPermissionHasNoParentPaths(): void
    {
        $this->createTestAssetFolders();
        $this->createTestDataObjectFolders();
        $this->createTestDocumentFolders();

        $user = $this->createUserWithWorkspaces(
            ['/test-asset-folder-1/sub-folder-1' => true],
            ['/test-document-folder-2/sub-folder-2' => true],
            ['/test-object-folder-3/sub-folder-3' => true],
            true
        );

        $this->assertElementSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
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

        $this->assertElementSearchResultFolders([
            '/test-asset-folder-1/sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-2',
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-3',
            '/test-document-folder-2/sub-folder-2',
            '/test-document-folder-2/sub-folder-2/sub-sub-folder-2',
            '/test-document-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/test-object-folder-3/sub-folder-3',
            '/test-object-folder-3/sub-folder-3/sub-sub-folder-3',
            '/test-object-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ], $user, PermissionTypes::VIEW);
    }

    public function testParentPathsAreOnlyIncludedForListPermissionType(): void
    {
        $this->createTestAssetFolders();
        $this->createTestDataObjectFolders();
        $this->createTestDocumentFolders();

        $workspace = '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1';
        $user = $this->createUserWithPermission('assets');
        $workspaceObject = (new User\Workspace\Asset())
            ->setCid((int) Db::get()->fetchOne(self::ASSET_ID_BY_PATH_QUERY, [$workspace]))
            ->setCpath($workspace)
            ->setUserId($user->getId())
            ->setList(true)
            ->setView(true)
            ->setPublish(true)
            ->setDelete(true)
            ->setRename(true)
            ->setCreate(true)
            ->setSettings(true)
            ->setVersions(true)
            ->setProperties(true);
        $workspaceObject->save();
        $user->setWorkspacesAsset([$workspaceObject]);

        $subTree = [
            $workspace,
            $workspace . '/sub-sub-sub-folder-1',
            $workspace . '/sub-sub-sub-folder-2',
            $workspace . '/sub-sub-sub-folder-3',
        ];

        $this->assertAssetSearchResultFolders(
            array_merge(['/', '/test-asset-folder-1', '/test-asset-folder-1/sub-folder-1'], $subTree),
            $user
        );

        foreach (PermissionTypes::cases() as $permissionType) {
            if ($permissionType === PermissionTypes::LIST) {
                continue;
            }

            $this->assertAssetSearchResultFolders($subTree, $user, $permissionType);
        }
    }

    public function testWorkspacesOfRolesIncludeParentPathsOnlyForListPermission(): void
    {
        $this->createTestAssetFolders();

        $workspace = '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1';
        $role = new User\Role();
        $role
            ->setName('test-role-' . uniqid())
            ->setPermissions(['assets'])
            ->save();
        $this->createdUsers[] = $role;

        $roleWorkspace = (new User\Workspace\Asset())
            ->setCid((int) Db::get()->fetchOne(self::ASSET_ID_BY_PATH_QUERY, [$workspace]))
            ->setCpath($workspace)
            ->setUserId($role->getId())
            ->setList(true)
            ->setView(true);
        $roleWorkspace->save();
        $role->setWorkspacesAsset([$roleWorkspace]);

        $user = new User();
        $user
            ->setUsername('test-user-' . uniqid())
            ->setRoles([$role->getId()])
            ->save();
        $this->createdUsers[] = $user;

        $this->assertAssetSearchResultFolders([
            '/',
            '/test-asset-folder-1',
            '/test-asset-folder-1/sub-folder-1',
            $workspace,
            $workspace . '/sub-sub-sub-folder-1',
            $workspace . '/sub-sub-sub-folder-2',
            $workspace . '/sub-sub-sub-folder-3',
        ], $user);

        $this->assertAssetSearchResultFolders([
            $workspace,
            $workspace . '/sub-sub-sub-folder-1',
            $workspace . '/sub-sub-sub-folder-2',
            $workspace . '/sub-sub-sub-folder-3',
        ], $user, PermissionTypes::VIEW);

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        $parentId = (int) Db::get()->fetchOne(self::ASSET_ID_BY_PATH_QUERY, ['/test-asset-folder-1/sub-folder-1']);
        $allowedId = (int) Db::get()->fetchOne(self::ASSET_ID_BY_PATH_QUERY, [$workspace]);

        // the list search above cached both folders, byId() checks the view permission through the role
        $this->assertNull($searchService->byId($parentId, $user));
        $this->assertNotNull($searchService->byId($allowedId, $user));
    }

    public function testByIdForceReloadDoesNotServeItemsToUsersWithoutViewPermission(): void
    {
        $this->createTestAssetFolders();

        $workspace = '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1';
        $viewUser = $this->createUserWithAssetWorkspaces([$workspace => true], true);
        $listOnlyUser = $this->createUserWithAssetWorkspaces([$workspace => true]);
        $allowedId = (int) Db::get()->fetchOne(self::ASSET_ID_BY_PATH_QUERY, [$workspace]);

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');

        $this->assertNull($searchService->byId($allowedId, $listOnlyUser, true));
        // an earlier forced reload by a user without view permission does not hide the item from others
        $viewItem = $searchService->byId($allowedId, $viewUser);
        $this->assertNotNull($viewItem);

        $reloadedItem = $searchService->byId($allowedId, $viewUser, true);
        $this->assertNotNull($reloadedItem);
        $this->assertNull($searchService->byId($allowedId, $listOnlyUser));
        $this->assertNotNull($searchService->byId($allowedId, $viewUser));
    }

    public function testHandleWorkspaceQueryDeclinedPathsForViewPermission(): void
    {
        $this->createTestAssetFolders();

        $user = $this->createUserWithAssetWorkspaces([
            '/' => true,
            '/test-asset-folder-1' => false,
            '/test-asset-folder-1/sub-folder-1/sub-sub-folder-1' => true,
        ], true);

        $this->assertAssetSearchResultFolders([
            '/',
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
        ], $user, PermissionTypes::VIEW);
    }

    public function testHandleDataObjectWorkspaceQueryParentPathsOnlyForListPermission(): void
    {
        $this->createTestDataObjectFolders();

        $user = $this->createUserWithDataObjectWorkspaces(
            ['/test-object-folder-1/sub-folder-1/sub-sub-folder-1' => true],
            true
        );

        $this->assertDataObjectSearchResultFolders([
            '/',
            '/test-object-folder-1',
            '/test-object-folder-1/sub-folder-1',
            '/test-object-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-object-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
        ], $user);

        $this->assertDataObjectSearchResultFolders([
            '/test-object-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-object-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
        ], $user, PermissionTypes::VIEW);

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $this->assertByIdReturnsOnlyViewableItems(
            $searchService,
            fn () => array_map(
                fn (DataObjectSearchResultItem $item) => $item->getId(),
                $searchService->search(
                    $searchProvider->createDataObjectSearch()->setUser(User::getByName('admin'))
                )->getItems()
            ),
            DataObject::getByPath('/test-object-folder-1/sub-folder-1')->getId(),
            DataObject::getByPath('/test-object-folder-1/sub-folder-1/sub-sub-folder-1')->getId(),
            $user,
            $this->createUserWithDataObjectWorkspaces(['/test-object-folder-1/sub-folder-1/sub-sub-folder-1' => true])
        );
    }

    public function testHandleDataObjectWorkspaceQueryDeclinedPathsForViewPermission(): void
    {
        $this->createTestDataObjectFolders();

        $user = $this->createUserWithDataObjectWorkspaces([
            '/' => true,
            '/test-object-folder-1' => false,
            '/test-object-folder-1/sub-folder-1/sub-sub-folder-1' => true,
        ], true);

        $this->assertDataObjectSearchResultFolders([
            '/',
            '/test-object-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-object-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-object-folder-2',
            '/test-object-folder-2/sub-folder-2',
            '/test-object-folder-2/sub-folder-2/sub-sub-folder-2',
            '/test-object-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/test-object-folder-3',
            '/test-object-folder-3/sub-folder-3',
            '/test-object-folder-3/sub-folder-3/sub-sub-folder-3',
            '/test-object-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ], $user, PermissionTypes::VIEW);
    }

    public function testHandleDocumentWorkspaceQueryParentPathsOnlyForListPermission(): void
    {
        $this->createTestDocumentFolders();

        $user = $this->createUserWithDocumentWorkspaces(
            ['/test-document-folder-1/sub-folder-1/sub-sub-folder-1' => true],
            true
        );

        $this->assertDocumentSearchResultFolders([
            '/',
            '/test-document-folder-1',
            '/test-document-folder-1/sub-folder-1',
            '/test-document-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-document-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
        ], $user);

        $this->assertDocumentSearchResultFolders([
            '/test-document-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-document-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
        ], $user, PermissionTypes::VIEW);

        /** @var DocumentSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.document-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $this->assertByIdReturnsOnlyViewableItems(
            $searchService,
            fn () => array_map(
                fn (DocumentSearchResultItem $item) => $item->getId(),
                $searchService->search(
                    $searchProvider->createDocumentSearch()->setUser(User::getByName('admin'))
                )->getItems()
            ),
            Document::getByPath('/test-document-folder-1/sub-folder-1')->getId(),
            Document::getByPath('/test-document-folder-1/sub-folder-1/sub-sub-folder-1')->getId(),
            $user,
            $this->createUserWithDocumentWorkspaces(['/test-document-folder-1/sub-folder-1/sub-sub-folder-1' => true])
        );
    }

    public function testHandleDocumentWorkspaceQueryDeclinedPathsForViewPermission(): void
    {
        $this->createTestDocumentFolders();

        $user = $this->createUserWithDocumentWorkspaces([
            '/' => true,
            '/test-document-folder-1' => false,
            '/test-document-folder-1/sub-folder-1/sub-sub-folder-1' => true,
        ], true);

        $this->assertDocumentSearchResultFolders([
            '/',
            '/test-document-folder-1/sub-folder-1/sub-sub-folder-1',
            '/test-document-folder-1/sub-folder-1/sub-sub-folder-1/sub-sub-sub-folder-1',
            '/test-document-folder-2',
            '/test-document-folder-2/sub-folder-2',
            '/test-document-folder-2/sub-folder-2/sub-sub-folder-2',
            '/test-document-folder-2/sub-folder-2/sub-sub-folder-2/sub-sub-sub-folder-2',
            '/test-document-folder-3',
            '/test-document-folder-3/sub-folder-3',
            '/test-document-folder-3/sub-folder-3/sub-sub-folder-3',
            '/test-document-folder-3/sub-folder-3/sub-sub-folder-3/sub-sub-sub-folder-3',
        ], $user, PermissionTypes::VIEW);
    }

    /**
     * Uses the core permission check as the expected result, so that list and view searches stay in line with it,
     * also for allowed sub paths below declined workspaces.
     *
     * @see https://github.com/pimcore/generic-data-index-bundle/issues/73
     */
    public function testAssetSearchMatchesCorePermissionCheck(): void
    {
        $this->createTestAssetFolders();

        foreach ($this->getWorkspaceSetups() as $setup => $workspaces) {
            $user = $this->createUserWithAssetWorkspaces($workspaces, true);

            $this->assertAssetSearchMatchesCorePermissionCheck($setup, $user, PermissionTypes::LIST);
            $this->assertAssetSearchMatchesCorePermissionCheck($setup, $user, PermissionTypes::VIEW);
        }
    }

    /**
     * @return array<string, array<string, bool>> list and view permission per workspace path, by setup name
     */
    private function getWorkspaceSetups(): array
    {
        $folder1 = '/test-asset-folder-1';
        $folder2 = '/test-asset-folder-2';
        $folder3 = '/test-asset-folder-3';
        $sub1 = $folder1 . '/sub-folder-1';
        $subSub1 = $sub1 . '/sub-sub-folder-1';
        $subSubSub1 = $subSub1 . '/sub-sub-sub-folder-1';
        $subSubSub2 = $subSub1 . '/sub-sub-sub-folder-2';
        $subSubSub3 = $subSub1 . '/sub-sub-sub-folder-3';

        return [
            'two allowed folders' => [$folder1 => true, $folder2 => true],
            'allowed folder with allowed sub folder' => [$folder1 => true, $sub1 => true],
            'allowed leaf folder only' => [$subSubSub1 => true],
            'allowed sub folder only' => [$subSub1 => true],
            'root with declined folder' => ['/' => true, $folder2 => false],
            'root with declined folder and sub folder' => ['/' => true, $folder1 => false, $sub1 => false],
            'root with declined leaf folder' => ['/' => true, $subSubSub3 => false],
            'declined sub folder' => [$folder1 => true, $sub1 => false],
            'declined sub sub folder' => [$folder1 => true, $subSub1 => false],
            'allowed below declined' => [$folder1 => true, $sub1 => false, $subSub1 => true],
            'allowed two levels below declined' => [$folder1 => true, $sub1 => false, $subSubSub1 => true],
            'declined below allowed below declined' => [
                $folder1 => true, $sub1 => false, $subSub1 => true, $subSubSub1 => false,
            ],
            'allowed below declined below root' => [
                '/' => true, $folder1 => false, $sub1 => true, $subSub1 => false, $folder2 => false,
                $folder3 => false,
            ],
            'allowed below two declined levels' => [
                $folder1 => true, $sub1 => false, $subSub1 => false, $subSubSub1 => true,
            ],
            'alternating declined and allowed without root' => [
                '/' => false, $folder1 => false, $sub1 => true, $subSub1 => false, $subSubSub1 => true,
                $subSubSub2 => false,
            ],
            'allowed two levels below declined below root' => [
                '/' => true, $folder1 => false, $subSub1 => true, $subSubSub2 => false, $folder2 => false,
                $folder3 => false,
            ],
            'allowed below declined without root' => [$folder1 => false, $subSub1 => true],
            'allowed sub folder below root with declined folder' => [
                '/' => true, $folder1 => false, $subSub1 => true,
            ],
        ];
    }

    private function assertAssetSearchMatchesCorePermissionCheck(
        string $setup,
        User $user,
        PermissionTypes $permissionType
    ): void {
        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $searchResult = $searchService->search(
            $searchProvider->createAssetSearch()->setUser($user)->setPageSize(100),
            $permissionType
        );

        $allowedPaths = [];
        $byIdPaths = [];
        foreach ((new Asset\Listing())->load() as $asset) {
            if ($asset->isAllowed($permissionType->value, $user)) {
                $allowedPaths[] = $asset->getRealFullPath();
            }
            if ($permissionType === PermissionTypes::VIEW && $searchService->byId($asset->getId(), $user)) {
                $byIdPaths[] = $asset->getRealFullPath();
            }
        }

        $this->assertResultItemPaths(
            $allowedPaths,
            $searchResult->getItems(),
            $setup . ': ' . $permissionType->value . ' search'
        );

        if ($permissionType === PermissionTypes::VIEW) {
            sort($allowedPaths);
            sort($byIdPaths);
            $this->assertSame($allowedPaths, $byIdPaths, $setup . ': byId()');
        }
    }

    /**
     * Expects that a list search for $user ran before, so both elements are in the runtime cache that byId() reads.
     *
     * @param callable(): int[] $searchIdsAsAdmin
     */
    private function assertByIdReturnsOnlyViewableItems(
        AssetSearchServiceInterface|DataObjectSearchServiceInterface|DocumentSearchServiceInterface $searchService,
        callable $searchIdsAsAdmin,
        int $parentId,
        int $allowedId,
        User $user,
        User $listOnlyUser
    ): void {
        $this->assertNull($searchService->byId($parentId, $user));
        $allowedItem = $searchService->byId($allowedId, $user);
        $this->assertNotNull($allowedItem);
        // once confirmed by the view search, the item is served from the runtime cache
        $this->assertSame($allowedItem, $searchService->byId($allowedId, $user));
        $this->assertNull($searchService->byId($parentId, $user));

        // the item cached for $user is not returned to a user without view permission
        $this->assertNull($searchService->byId($allowedId, $listOnlyUser));
        $this->assertSame($allowedItem, $searchService->byId($allowedId, $user));

        // a search by another user replaces the cached item, so byId() searches again for this user
        $this->assertContains($allowedId, $searchIdsAsAdmin());
        $reloadedItem = $searchService->byId($allowedId, $user);
        $this->assertNotNull($reloadedItem);
        $this->assertNotSame($allowedItem, $reloadedItem);
        $this->assertFalse($reloadedItem->getPermissions()->isDelete());
    }

    private function assertAssetSearchResultFolders(
        array $expectedPaths,
        User $user,
        PermissionTypes $permissionType = PermissionTypes::LIST
    ) {
        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->setUser($user)
        ;
        $searchResult = $searchService->search($assetSearch, $permissionType);

        $this->assertResultItemPaths($expectedPaths, $searchResult->getItems());
    }

    private function assertDataObjectSearchResultFolders(
        array $expectedPaths,
        User $user,
        PermissionTypes $permissionType = PermissionTypes::LIST
    ): void {
        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $searchResult = $searchService->search(
            $searchProvider->createDataObjectSearch()->setUser($user),
            $permissionType
        );

        $this->assertResultItemPaths($expectedPaths, $searchResult->getItems());
    }

    private function assertDocumentSearchResultFolders(
        array $expectedPaths,
        User $user,
        PermissionTypes $permissionType = PermissionTypes::LIST
    ): void {
        /** @var DocumentSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.document-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $searchResult = $searchService->search(
            $searchProvider->createDocumentSearch()->setUser($user),
            $permissionType
        );

        $this->assertResultItemPaths($expectedPaths, $searchResult->getItems());
    }

    /**
     * @param ElementSearchResultItemInterface[] $items
     */
    private function assertResultItemPaths(array $expectedPaths, array $items, string $message = ''): void
    {
        $paths = array_map(function (ElementSearchResultItemInterface $item) {
            return $item->getPath() . $item->getKey();
        }, $items);

        sort($expectedPaths);
        sort($paths);

        $this->assertEquals($expectedPaths, $paths, $message);
    }

    private function assertElementSearchResultFolders(
        array $expectedPaths,
        User $user,
        PermissionTypes $permissionType = PermissionTypes::LIST
    ): void {
        /** @var ElementSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.element-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $elementSearch = $searchProvider
            ->createElementSearch()
            ->setUser($user)
        ;
        $searchResult = $searchService->search($elementSearch, $permissionType);

        $this->assertResultItemPaths($expectedPaths, $searchResult->getItems());
    }

    private function createUserWithAssetWorkspaces(array $workspaces, bool $withView = false): User
    {
        $user = $this->createUserWithPermission('assets');
        $user->setWorkspacesAsset($this->saveWorkspaces(
            $user,
            User\Workspace\Asset::class,
            self::ASSET_ID_BY_PATH_QUERY,
            $workspaces,
            $withView
        ));

        return $user;
    }

    private function createUserWithDataObjectWorkspaces(array $workspaces, bool $withView = false): User
    {
        $user = $this->createUserWithPermission('objects');
        $user->setWorkspacesObject($this->saveWorkspaces(
            $user,
            User\Workspace\DataObject::class,
            self::DATA_OBJECT_ID_BY_PATH_QUERY,
            $workspaces,
            $withView
        ));

        return $user;
    }

    private function createUserWithDocumentWorkspaces(array $workspaces, bool $withView = false): User
    {
        $user = $this->createUserWithPermission('documents');
        $user->setWorkspacesDocument($this->saveWorkspaces(
            $user,
            User\Workspace\Document::class,
            self::DOCUMENT_ID_BY_PATH_QUERY,
            $workspaces,
            $withView
        ));

        return $user;
    }

    private function createUserWithPermission(string $permission): User
    {
        $user = new User();
        $user
            ->setPermission($permission, true)
            ->setUsername('test-user-' . uniqid())
            ->save();
        $this->createdUsers[] = $user;

        return $user;
    }

    /**
     * @template T of User\Workspace\AbstractWorkspace
     *
     * @param class-string<T> $workspaceClass
     * @param array<string, bool> $workspaces list permission per workspace path
     *
     * @return T[]
     */
    private function saveWorkspaces(
        User $user,
        string $workspaceClass,
        string $idQuery,
        array $workspaces,
        bool $withView
    ): array {
        $workspaceArray = [];
        foreach ($workspaces as $workspace => $permission) {
            $workspaceObject = (new $workspaceClass())
                ->setList($permission)
                ->setView($withView && $permission)
                ->setCpath($workspace)
                ->setCid(Db::get()->fetchOne($idQuery, [$workspace]))
                ->setUserId($user->getId());

            $workspaceObject->save();
            $workspaceArray[] = $workspaceObject;
        }

        return $workspaceArray;
    }

    private function createUserWithWorkspaces(
        array $assetWorkspaces,
        array $documentWorkspaces,
        array $objectWorkspaces,
        bool $withView = false
    ): User {
        $user = new User();
        $user
            ->setPermission('assets', true)
            ->setPermission('documents', true)
            ->setPermission('objects', true)
            ->setUsername('test-user-' . uniqid())
            ->save();
        $this->createdUsers[] = $user;

        $user->setWorkspacesAsset($this->saveWorkspaces(
            $user,
            User\Workspace\Asset::class,
            self::ASSET_ID_BY_PATH_QUERY,
            $assetWorkspaces,
            $withView
        ));
        $user->setWorkspacesDocument($this->saveWorkspaces(
            $user,
            User\Workspace\Document::class,
            self::DOCUMENT_ID_BY_PATH_QUERY,
            $documentWorkspaces,
            $withView
        ));
        $user->setWorkspacesObject($this->saveWorkspaces(
            $user,
            User\Workspace\DataObject::class,
            self::DATA_OBJECT_ID_BY_PATH_QUERY,
            $objectWorkspaces,
            $withView
        ));

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
        $folder = Document::getByPath('/');
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
