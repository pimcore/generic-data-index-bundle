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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\Sort;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Search\SortDirection;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\OpenSearch\ResultWindowTooLargeException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\Sort\TreeSortHandlers;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Tests\Support\Util\TestHelper;

final class SortModifierTest extends \Codeception\Test\Unit
{
    /**
     * @var \Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester
     */
    protected $tester;

    private bool $indexSettingsChanged = false;

    protected function _before()
    {
        $this->tester->enableSynchronousProcessing();
    }

    protected function _after()
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



    public function testHandleSortByPageNumber()
    {
        $assets = $this->createAssets();
        $fullPaths = [];
        foreach ($assets as $asset) {
            $fullPaths[] = $asset->getFullPath();
        }
        /** @var SearchIndexConfigServiceInterface $searchIndexConfigService */
        $searchIndexConfigService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);
        /** @var TreeSortHandlers $searchProvider */
        $treeSort = $this->tester->grabService(TreeSortHandlers::class);

        // Make sure that sorting by page number is executed
        $indexName = $searchIndexConfigService->getIndexName('asset');
        $this->tester->setIndexResultWindow($indexName, 2);
        $treeSort->setItemsLimit(2);
        $this->indexSettingsChanged = true;

        // Sort direction of the result should remain same even though the search order was reversed
        rsort($fullPaths);
        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->setPageSize(2)
            ->setPage(5)
            ->addModifier(new OrderByFullPath(SortDirection::DESC))
        ;
        $searchResult = $searchService->search($assetSearch);

        $this->assertCount(2, $searchResult->getItems());
        $this->assertSame($fullPaths[8], $searchResult->getItems()[0]->getFullPath());
        $this->assertSame($fullPaths[9], $searchResult->getItems()[1]->getFullPath());


        sort($fullPaths);
        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->setPageSize(2)
            ->setPage(5)
            ->addModifier(new OrderByFullPath())
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(2, $searchResult->getItems());
        $this->assertSame($fullPaths[8], $searchResult->getItems()[0]->getFullPath());
        $this->assertSame($fullPaths[9], $searchResult->getItems()[1]->getFullPath());

        //Without initial sort is page sort not executed and because of the result window we expect an exception
        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->setPageSize(2)
            ->setPage(5)
        ;

        $this->expectException(ResultWindowTooLargeException::class);
        $searchService->search($assetSearch);
    }

    // tests
    public function testOrderByFullPath()
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

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new OrderByFullPath())
        ;
        $searchResult = $searchService->search($assetSearch);

        $resultFullPaths = array_map(function ($asset) {
            return $asset->getFullPath();
        }, $searchResult->getItems());

        $this->assertEquals($fullPaths, $resultFullPaths);

        rsort($fullPaths);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new OrderByFullPath(SortDirection::DESC))
        ;
        $searchResult = $searchService->search($assetSearch);

        $resultFullPaths = array_map(function ($asset) {
            return $asset->getFullPath();
        }, $searchResult->getItems());

        $this->assertEquals($fullPaths, $resultFullPaths);

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
