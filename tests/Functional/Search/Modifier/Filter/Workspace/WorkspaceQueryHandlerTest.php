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
    private function assertResultItemPaths(array $expectedPaths, array $items): void
    {
        $paths = array_map(function (ElementSearchResultItemInterface $item) {
            return $item->getPath() . $item->getKey();
        }, $items);

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
        array $objectWorkspaces
    ): User {
        $user = new User();
        $user
            ->setPermission('assets', true)
            ->setPermission('documents', true)
            ->setPermission('objects', true)
            ->setUsername('test-user-' . uniqid())
            ->save();

        $user->setWorkspacesAsset($this->saveWorkspaces(
            $user,
            User\Workspace\Asset::class,
            self::ASSET_ID_BY_PATH_QUERY,
            $assetWorkspaces,
            false
        ));
        $user->setWorkspacesDocument($this->saveWorkspaces(
            $user,
            User\Workspace\Document::class,
            self::DOCUMENT_ID_BY_PATH_QUERY,
            $documentWorkspaces,
            false
        ));
        $user->setWorkspacesObject($this->saveWorkspaces(
            $user,
            User\Workspace\DataObject::class,
            self::DATA_OBJECT_ID_BY_PATH_QUERY,
            $objectWorkspaces,
            false
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
