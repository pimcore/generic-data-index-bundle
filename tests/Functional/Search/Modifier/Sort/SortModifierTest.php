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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\Sort;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Search\SortDirection;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\OpenSearch\ResultWindowTooLargeException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\OpenSearch\SearchFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\OrderByField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByIndexField;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\Sort\TreeSortHandlers;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Tests\Support\Util\TestHelper;

final class SortModifierTest extends Unit
{
    protected IndexTester $tester;
    
    private AssetSearchServiceInterface $assetSearchService;
    
    private DataObjectSearchServiceInterface $dataObjectSearchService;
    
    private SearchProviderInterface $searchProvider;

    private bool $indexSettingsChanged = false;

    protected function _before(): void
    {
        $this->searchProvider = $this->tester->grabService(SearchProviderInterface::class);
        $this->assetSearchService = $this->tester->grabService(
            'generic-data-index.test.service.asset-search-service'
        );
        $this->dataObjectSearchService = $this->tester->grabService(
            'generic-data-index.test.service.data-object-search-service'
        );
        $this->tester->enableSynchronousProcessing();
    }

    protected function _after(): void
    {
        if ($this->indexSettingsChanged) {
            $this->indexSettingsChanged = false;
            $this->tester->resetIndexWindowSettings('asset');
        }

        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    public function testHandleSortByPageNumber(): void
    {
        $assets = $this->createAssets();
        $fullPaths = [];
        foreach ($assets as $asset) {
            $fullPaths[] = $asset->getFullPath();
        }
        /** @var SearchIndexConfigServiceInterface $searchIndexConfigService */
        $searchIndexConfigService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        /** @var TreeSortHandlers $treeSort */
        $treeSort = $this->tester->grabService(TreeSortHandlers::class);

        // Make sure that sorting by page number is executed
        $indexName = $searchIndexConfigService->getIndexName('asset');
        $this->tester->setIndexResultWindow($indexName, 2);
        $treeSort->setItemsLimit(2);
        $this->indexSettingsChanged = true;

        // Sort direction of the result should remain same even though the search order was reversed
        rsort($fullPaths);
        $assetSearch = $this->searchProvider
            ->createAssetSearch()
            ->setPageSize(2)
            ->setPage(5)
            ->addModifier(new OrderByFullPath(SortDirection::DESC))
        ;
        $searchResult = $this->assetSearchService->search($assetSearch);

        $this->assertCount(2, $searchResult->getItems());
        $this->assertSame($fullPaths[8], $searchResult->getItems()[0]->getFullPath());
        $this->assertSame($fullPaths[9], $searchResult->getItems()[1]->getFullPath());

        sort($fullPaths);
        $assetSearch = $this->searchProvider
            ->createAssetSearch()
            ->setPageSize(2)
            ->setPage(5)
            ->addModifier(new OrderByFullPath())
        ;
        $searchResult = $this->assetSearchService->search($assetSearch);
        $this->assertCount(2, $searchResult->getItems());
        $this->assertSame($fullPaths[8], $searchResult->getItems()[0]->getFullPath());
        $this->assertSame($fullPaths[9], $searchResult->getItems()[1]->getFullPath());

        //Without initial sort is page sort not executed and because of the result window we expect an exception
        $assetSearch = $this->searchProvider
            ->createAssetSearch()
            ->setPageSize(2)
            ->setPage(5)
        ;

        $this->expectException(ResultWindowTooLargeException::class);
        $this->assetSearchService->search($assetSearch);
    }

    // tests
    public function testOrderByFullPath(): void
    {
        $asset = TestHelper::createImageAsset();
        $asset2 = TestHelper::createImageAsset();
        $asset3 = TestHelper::createImageAsset();

        $fullPaths = [
            $asset->getFullPath(),
            $asset2->getFullPath(),
            $asset3->getFullPath(),
        ];
        sort($fullPaths);

        $assetSearch = $this->searchProvider
            ->createAssetSearch()
            ->addModifier(new OrderByFullPath())
        ;
        $searchResult = $this->assetSearchService->search($assetSearch);

        $resultFullPaths = array_map(static function ($asset) {
            return $asset->getFullPath();
        }, $searchResult->getItems());

        $this->assertEquals($fullPaths, $resultFullPaths);

        rsort($fullPaths);

        $assetSearch = $this->searchProvider
            ->createAssetSearch()
            ->addModifier(new OrderByFullPath(SortDirection::DESC))
        ;
        $searchResult = $this->assetSearchService->search($assetSearch);

        $resultFullPaths = array_map(static function ($asset) {
            return $asset->getFullPath();
        }, $searchResult->getItems());

        $this->assertEquals($fullPaths, $resultFullPaths);

    }

    public function testOrderByIndex(): void
    {
        $object = TestHelper::createEmptyObject()->setIndex(1)->save();
        $object2 = TestHelper::createEmptyObject()->setIndex(2)->save();
        $object3 = TestHelper::createEmptyObject()->setIndex(0)->save();
        $sortedIds = [$object3->getId(), $object->getId(), $object2->getId()];

        $dataObjectSearch = $this->searchProvider
            ->createDataObjectSearch()
            ->addModifier(new OrderByIndexField())
        ;

        $results = $this->dataObjectSearchService->search($dataObjectSearch);
        $resultIds = array_map(static function ($object) {
            return $object->getId();
        }, $results->getItems());

        $this->assertEquals($sortedIds, $resultIds);

        $object->setIndex(3)->save();
        $results = $this->dataObjectSearchService->search($dataObjectSearch);
        $resultIds = array_map(static function ($object) {
            return $object->getId();
        }, $results->getItems());

        $this->assertEquals($object->getId(), $resultIds[2]);
    }

    // tests
    public function testOrderByField(): void
    {
        $asset = TestHelper::createImageAsset();
        $asset2 = TestHelper::createImageAsset();
        $asset3 = TestHelper::createImageAsset();

        $keys = [
            $asset->getKey(),
            $asset2->getKey(),
            $asset3->getKey(),
        ];
        sort($keys);

        $assetSearch = $this->searchProvider
            ->createAssetSearch()
            ->addModifier(new OrderByField('key'))
        ;
        $searchResult = $this->assetSearchService->search($assetSearch);
        $resultKeys = array_map(static function ($asset) {
            return $asset->getKey();
        }, $searchResult->getItems());
        $this->assertEquals($keys, $resultKeys);

        rsort($keys);
        $assetSearch = $this->searchProvider
            ->createAssetSearch()
            ->addModifier(new OrderByField('key', SortDirection::DESC))
        ;
        $searchResult = $this->assetSearchService->search($assetSearch);
        $resultKeys = array_map(static function ($asset) {
            return $asset->getKey();
        }, $searchResult->getItems());
        $this->assertEquals($keys, $resultKeys);

        $assetSearch = $this->searchProvider
            ->createAssetSearch()
            ->addModifier(new OrderByField('system_fields.key.sort', SortDirection::DESC, false))
        ;

        $searchResult = $this->assetSearchService->search($assetSearch);
        $resultKeys = array_map(static function ($asset) {
            return $asset->getKey();
        }, $searchResult->getItems());
        $this->assertEquals($keys, $resultKeys);

        $assetSearch = $this->searchProvider
            ->createAssetSearch()
            ->addModifier(new OrderByField('key', SortDirection::ASC, false))
        ;
        $this->expectException(SearchFailedException::class);
        $this->assetSearchService->search($assetSearch);

    }

    private function createAssets(): array
    {
        $assets = [];
        for ($i = 0; $i < 10; $i++) {
            $assets[] = TestHelper::createImageAsset();
        }

        return $assets;
    }
}
