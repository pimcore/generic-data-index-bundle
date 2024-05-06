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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\Aggregation;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Asset\AssetMetaDataAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Tests\Support\Util\TestHelper;

final class AssetMetadataAggregationTest extends \Codeception\Test\Unit
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
    public function testAssetMetadataAggregationSelect()
    {
        $asset = TestHelper::createImageAsset();
        $asset->addMetadata('testSelect', 'select', 'value1');
        $asset->save();

        $asset2 = TestHelper::createImageAsset();
        $asset2->addMetadata('testSelect', 'select', 'value2');
        $asset2->save();

        $asset3 = TestHelper::createImageAsset();
        $asset3->addMetadata('testSelect', 'select', 'value1');
        $asset3->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $aggregation = new AssetMetaDataAggregation('testSelect', 'select');

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier($aggregation)
        ;

        $searchResult = $searchService->search($assetSearch);
        $aggregationResult = $searchResult->getAggregation($aggregation->getAggregationName());

        $this->assertCount(2, $aggregationResult->getBuckets());
        $this->assertEquals('value1', $aggregationResult->getBuckets()[0]->getKey());
        $this->assertEquals(2, $aggregationResult->getBuckets()[0]->getDocCount());
        $this->assertEquals('value2', $aggregationResult->getBuckets()[1]->getKey());
        $this->assertEquals(1, $aggregationResult->getBuckets()[1]->getDocCount());
    }
}
