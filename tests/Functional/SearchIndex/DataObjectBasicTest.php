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

namespace Functional\SearchIndex;

use OpenSearch\Common\Exceptions\Missing404Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Tests\Support\Util\TestHelper;

class DataObjectBasicTest extends \Codeception\Test\Unit
{
    /**
     * @var \Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester
     */
    protected $tester;

    private SearchIndexConfigServiceInterface $searchIndexConfigService;

    protected function _before()
    {
        $this->searchIndexConfigService = $this->tester->grabService(
            SearchIndexConfigServiceInterface::class
        );
        $this->tester->enableSynchronousProcessing();
        $this->tester->clearQueue();
    }

    protected function _after()
    {
        $this->disableInheritance();
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    // tests
    public function testIndexingWithInheritanceSynchronous()
    {
        $object = $this->createObjectWithInheritance();
        $indexName = $this->searchIndexConfigService->getIndexName($object->getClassName());

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

    public function testIndexingWithInheritanceAsynchronous()
    {
        $this->tester->disableSynchronousProcessing();
        // create object
        $object = $this->createObjectWithInheritance();
        $indexName = $this->searchIndexConfigService->getIndexName($object->getClassName());

        // check indexed
        $this->expectException(Missing404Exception::class);
        $this->tester->checkIndexEntry($object->getId(), $indexName);

        $this->assertNotEmpty(
            Db::get()->fetchOne(
                'select count(elementId) from generic_data_index_queue where elementId = ? and elementType="object"',
                [$object->getId()]
            )
        );
        $this->tester->runCommand('messenger:consume', ['--limit'=>2], ['pimcore_generic_data_index_queue']);
        $response = $this->tester->checkIndexEntry($object->getId(), $indexName);
        $this->assertEquals($object->getKey(), $response['_source']['system_fields']['key']);
    }

    public function testIndexingWithoutInheritanceSynchronous()
    {
        $object = TestHelper::createEmptyObject();
        $this->assertFalse($object->getClass()->getAllowInherit());
        $indexName = $this->searchIndexConfigService->getIndexName($object->getClassName());

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

    public function testIndexingWithoutInheritanceAsynchronous()
    {
        $this->tester->disableSynchronousProcessing();
        // create object
        $object = TestHelper::createEmptyObject();
        $this->assertFalse($object->getClass()->getAllowInherit());
        $indexName = $this->searchIndexConfigService->getIndexName($object->getClassName());

        // check indexed
        $this->expectException(Missing404Exception::class);
        $this->tester->checkIndexEntry($object->getId(), $indexName);

        $this->assertNotEmpty(
            Db::get()->fetchOne(
                'select count(elementId) from generic_data_index_queue where elementId = ? and elementType="object"',
                [$object->getId()]
            )
        );
        $this->tester->runCommand('messenger:consume', ['--limit'=>2], ['pimcore_generic_data_index_queue']);
        $response = $this->tester->checkIndexEntry($object->getId(), $indexName);
        $this->assertEquals($object->getKey(), $response['_source']['system_fields']['key']);
    }

    public function testDataObjectSearch()
    {
        $object = TestHelper::createEmptyObject();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $dataObjectSearch = $searchProvider
            ->createDataObjectSearch()
            ->setPageSize(20)
        ;

        $searchResult = $searchService->search($dataObjectSearch);

        $this->assertEquals(1, $searchResult->getPagination()->getTotalItems());
        $this->assertEquals(20, $searchResult->getPagination()->getPageSize());
        $this->assertCount(1, $searchResult->getItems());
        $this->assertIdArrayEquals([$object->getId()], $searchResult->getIds());
    }

    public function testSettingsStoreMapping()
    {
        /** @var SettingsStoreServiceInterface $searchProvider */
        $settingsStoreService = $this->tester->grabService(SettingsStoreServiceInterface::class);
        $object = TestHelper::createEmptyObject();
        $class = $object->getClass();
        $classId = $class->getId();
        $checkSum = 123;

        $settingsStoreService->storeClassMapping($classId, $checkSum);
        $this->assertEquals($checkSum, $settingsStoreService->getClassMappingCheckSum($classId));

        $settingsStoreService->removeClassMapping($classId);
        $this->assertNull($settingsStoreService->getClassMappingCheckSum($classId));

        $class->save();
        $this->tester->runCommand('messenger:consume', ['--limit'=>2], ['pimcore_generic_data_index_queue']);
        $checkSum = $settingsStoreService->getClassMappingCheckSum($classId);
        $this->assertNotNull($checkSum);

        $class->save();
        $this->tester->runCommand('messenger:consume', ['--limit'=>2], ['pimcore_generic_data_index_queue']);
        $this->assertEquals($checkSum, $settingsStoreService->getClassMappingCheckSum($classId));

        $input = new Input();
        $input->setName('settingsTest');
        $class->addFieldDefinition('settingsTest', $input);
        $class->save();
        $this->tester->runCommand('messenger:consume', ['--limit'=>2], ['pimcore_generic_data_index_queue']);
        $this->assertNotEquals($checkSum, $settingsStoreService->getClassMappingCheckSum($classId));
    }

    private function assertIdArrayEquals(array $ids1, array $ids2)
    {
        sort($ids1);
        sort($ids2);
        $this->assertEquals($ids1, $ids2);
    }

    private function createObjectWithInheritance(): Concrete
    {
        $object = TestHelper::createEmptyObject('', false);
        $class = $object->getClass();
        if ($class->getAllowInherit() === true) {
            return $object->save();
        }

        $class->setAllowInherit(true);
        $class->save();
        $object->save();

        $this->assertTrue($object->getClass()->getAllowInherit());

        return $object;
    }

    private function disableInheritance(): void
    {
        $object = TestHelper::createEmptyObject('', false);
        $class = $object->getClass();
        if ($class->getAllowInherit() === false) {
            return;
        }

        $class->setAllowInherit(false);
        $class->save();
    }
}
