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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional;

use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Tests\Support\Util\TestHelper;

class AssetTest extends \Codeception\Test\Unit
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

    public function testAssetIndexing()
    {
        /**
         * @var SearchIndexConfigServiceInterface $searchIndexConfigService
         */
        $searchIndexConfigService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        $indexName = $searchIndexConfigService->getIndexName('asset');

        // create asset
        $asset = TestHelper::createImageAsset();

        $this->tester->flushIndex();

        // check indexed
        $response = $this->tester->checkIndexEntry($asset->getId(), $indexName);
        $this->assertEquals($asset->getId(), $response['_source']['system_fields']['id']);
    }

    public function testAssetSearch()
    {
        // create asset
        TestHelper::createImageAsset();

        $this->tester->flushIndex();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService(AssetSearchServiceInterface::class);
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->setPageSize(20)
        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals(1, $searchResult->getPagination()->getTotalItems());
        $this->assertEquals(20, $searchResult->getPagination()->getPageSize());
        $this->assertCount(1, $searchResult->getItems());
    }
}
