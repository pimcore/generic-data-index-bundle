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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\Aggregation;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Tree\ChildrenCountAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultAggregationBucket;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Tests\Support\Util\TestHelper;

final class TreeAggregationTest extends \Codeception\Test\Unit
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
    public function testChildrenCountAggregation()
    {
        $asset = TestHelper::createImageAsset();
        $asset2 = TestHelper::createImageAsset();
        $asset3 = TestHelper::createImageAsset();
        $folder = TestHelper::createAssetFolder();

        $asset->setParent($folder)->save();
        $asset2->setParent($folder)->save();
        $asset3->setParent($folder)->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $aggregation = new ChildrenCountAggregation([$folder->getId()]);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier($aggregation)
        ;
        $searchResult = $searchService->search($assetSearch);

        $aggregationResult = $searchResult->getAggregation($aggregation->getAggregationName());

        array_map(function (SearchResultAggregationBucket $bucket) use ($folder) {
            $this->assertEquals($folder->getId(), $bucket->getKey());
            $this->assertEquals(3, $bucket->getDocCount());
        }, $aggregationResult->getBuckets());
    }
}
