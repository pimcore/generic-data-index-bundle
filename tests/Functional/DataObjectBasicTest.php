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
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Tests\Support\Util\TestHelper;

class DataObjectBasicTest extends \Codeception\Test\Unit
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

    public function testDataObjectIndexing()
    {

        // create object
        $object = TestHelper::createEmptyObject();

        /**
         * @var SearchIndexConfigServiceInterface $searchIndexConfigService
         */
        $searchIndexConfigService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        $indexName = $searchIndexConfigService->getIndexName($object->getClassName());

        // check indexed
        $response = $this->tester->checkIndexEntry($object->getId(), $indexName);
        $this->assertEquals($object->getId(), $response['_source']['system_fields']['id']);

        $object->setKey('my-test-object');
        $object->save();

        $response = $this->tester->checkIndexEntry($object->getId(), $indexName);
        $this->assertEquals('my-test-object', $response['_source']['system_fields']['key']);

        $object->delete();

        $this->expectException(Missing404Exception::class);
        $this->tester->checkIndexEntry($object->getId(), $indexName);

    }

    public function testDataObjectSearch()
    {
        $object = TestHelper::createEmptyObject();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createDataObjectSearch()
            ->setPageSize(20)
        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals(1, $searchResult->getPagination()->getTotalItems());
        $this->assertEquals(20, $searchResult->getPagination()->getPageSize());
        $this->assertCount(1, $searchResult->getItems());
        $this->assertIdArrayEquals([$object->getId()], $searchResult->getIds());
    }

    private function assertIdArrayEquals(array $ids1, array $ids2)
    {
        sort($ids1);
        sort($ids2);
        $this->assertEquals($ids1, $ids2);
    }
}
