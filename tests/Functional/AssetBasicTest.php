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

use OpenSearch\Common\Exceptions\Missing404Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem\Document;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem\Folder;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem\Image;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem\Video;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Tests\Support\Util\TestHelper;

class AssetBasicTest extends \Codeception\Test\Unit
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

        // check indexed
        $response = $this->tester->checkIndexEntry($asset->getId(), $indexName);
        $this->assertEquals($asset->getId(), $response['_source']['system_fields']['id']);

        $asset->setKey('test.jpg');
        $asset->save();

        $response = $this->tester->checkIndexEntry($asset->getId(), $indexName);
        $this->assertEquals('test.jpg', $response['_source']['system_fields']['key']);

        $asset->delete();

        $this->expectException(Missing404Exception::class);
        $this->tester->checkIndexEntry($asset->getId(), $indexName);

    }

    public function testAssetSearch()
    {
        $asset = TestHelper::createImageAsset();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
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
        $this->assertEquals([$asset->getId()], $searchResult->getIds());
    }

    public function testAssetSearchTypes()
    {
        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');

        $folder = TestHelper::createAssetFolder();
        $video = TestHelper::createVideoAsset();
        $document = TestHelper::createDocumentAsset();
        $image = TestHelper::createImageAsset();

        $this->assertInstanceOf(Document::class, $searchService->byId($document->getId()));
        $this->assertInstanceOf(Folder::class, $searchService->byId($folder->getId()));
        $this->assertInstanceOf(Image::class, $searchService->byId($image->getId()));
        $this->assertInstanceOf(Video::class, $searchService->byId($video->getId()));
    }
}
