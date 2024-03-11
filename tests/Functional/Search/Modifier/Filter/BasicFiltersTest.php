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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\Filter;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Asset\AssetMetaDataFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\ExcludeFoldersFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Tests\Support\Util\TestHelper;

class BasicFiltersTest extends \Codeception\Test\Unit
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
    public function testExcludeFolders()
    {
        $asset = TestHelper::createImageAsset();

        $folder = TestHelper::createAssetFolder();
        $asset->setParent($folder)->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(2, $searchResult->getItems());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new ExcludeFoldersFilter())
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(1, $searchResult->getItems());
        $this->assertNotEquals('folder', $searchResult->getItems()[0]->getType());
    }

    public function testIdFilter()
    {
        $asset = TestHelper::createImageAsset();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new IdFilter($asset->getId()))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(1, $searchResult->getItems());
        $this->assertEquals($asset->getId(), $searchResult->getItems()[0]->getId());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new IdFilter(123))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(0, $searchResult->getItems());
    }

    public function testIdsFilter()
    {
        $asset = TestHelper::createImageAsset();
        $asset2 = TestHelper::createImageAsset();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new IdsFilter([$asset->getId()]))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(1, $searchResult->getItems());
        $this->assertEquals($asset->getId(), $searchResult->getItems()[0]->getId());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new IdsFilter([$asset->getId(), $asset2->getId()]))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(2, $searchResult->getItems());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new IdsFilter([123]))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(0, $searchResult->getItems());
    }

}
