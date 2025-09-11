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

namespace Functional\SearchIndex;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem\Document;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem\Folder;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem\Image;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem\Video;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Db;
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
        $this->tester->checkDeletedIndexEntry($asset->getId(), $indexName);

    }

    public function testFolderIndexingAsynchronous()
    {
        $this->tester->disableSynchronousProcessing();
        $searchIndexConfigService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        $indexName = $searchIndexConfigService->getIndexName(IndexName::ASSET->value);
        $folder = TestHelper::createAssetFolder();

        $folder->setKey('my-test-folder');
        $folder->save();

        //Since the queue is processed asynchronously we need to run the worker here
        $this->tester->runCommand('messenger:consume', ['--limit'=>2], ['pimcore_generic_data_index_queue']);

        $this->assertGreaterThan(
            0,
            Db::get()->fetchOne(
                'select count(elementId) from generic_data_index_queue where elementId = ? and elementType="asset"',
                [$folder->getId()]
            )
        );

        $folder->delete();
        $this->tester->consume();
        $this->tester->checkDeletedIndexEntry($folder->getId(), $indexName);
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
