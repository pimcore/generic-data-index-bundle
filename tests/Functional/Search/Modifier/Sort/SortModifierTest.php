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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Tests\Support\Util\TestHelper;

final class SortModifierTest extends \Codeception\Test\Unit
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

        $resultFullPaths = array_map(function($asset) {
            return $asset->getFullPath();
        }, $searchResult->getItems());

        $this->assertEquals($fullPaths, $resultFullPaths);

        rsort($fullPaths);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new OrderByFullPath(SortDirection::DESC))
        ;
        $searchResult = $searchService->search($assetSearch);

        $resultFullPaths = array_map(function($asset) {
            return $asset->getFullPath();
        }, $searchResult->getItems());

        $this->assertEquals($fullPaths, $resultFullPaths);

    }
}
