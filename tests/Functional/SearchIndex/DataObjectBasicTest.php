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

namespace Functional\SearchIndex;

use OpenSearch\Common\Exceptions\Missing404Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\MappingTest;
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
        $child = $this->createChildObject($object);
        $indexName = $this->searchIndexConfigService->getIndexName($object->getClassName());

        // check indexed
        $response = $this->tester->checkIndexEntry($child->getId(), $indexName);
        $this->assertEquals($child->getId(), $response['_source']['system_fields']['id']);
        $this->assertEquals(
            $object->getId(),
            $this->getInheritedFieldsResponse($response)['input']['originId']
        );

        $object->setKey('my-test-object');
        $object->save();

        $response = $this->tester->checkIndexEntry($object->getId(), $indexName);
        $this->assertEquals('my-test-object', $response['_source']['system_fields']['key']);

        $child->setInput('Updated input');
        $child->save();

        $response = $this->tester->checkIndexEntry($child->getId(), $indexName);
        $this->assertEquals(
            'Updated input', $response['_source'][FieldCategory::STANDARD_FIELDS->value]['input']
        );
        $this->assertArrayNotHasKey(
            'input',
            $this->getInheritedFieldsResponse($response)
        );

        //Should also delete child element
        $object->delete();

        $this->expectException(Missing404Exception::class);
        $this->tester->checkIndexEntry($child->getId(), $indexName);
    }

    public function testIndexingWithInheritanceAsynchronous()
    {
        $this->tester->disableSynchronousProcessing();
        // create object
        $object = $this->createObjectWithInheritance();
        $child = $this->createChildObject($object);
        $indexName = $this->searchIndexConfigService->getIndexName($object->getClassName());

        // check indexed
        $this->assertNotEmpty(
            Db::get()->fetchOne(
                'select count(elementId) from generic_data_index_queue where elementId = ? and elementType="dataObject"',
                [$object->getId()]
            )
        );
        $this->tester->runCommand('messenger:consume', ['--limit'=>2], ['pimcore_generic_data_index_queue']);
        $response = $this->tester->checkIndexEntry($child->getId(), $indexName);
        $this->assertEquals($child->getKey(), $response['_source']['system_fields']['key']);
        $this->assertEquals(
            $object->getId(),
            $this->getInheritedFieldsResponse($response)['input']['originId']
        );
    }

    public function testIndexingWithInheritanceAsynchronousNoInheritance()
    {
        $this->tester->disableSynchronousProcessing();
        // create object
        $object = $this->createObjectWithInheritance();
        $child = $this->createChildObject($object);
        $indexName = $this->searchIndexConfigService->getIndexName($object->getClassName());

        // check indexed
        $this->assertNotEmpty(
            Db::get()->fetchOne(
                'select count(elementId) from generic_data_index_queue where elementId = ? and elementType="dataObject"',
                [$object->getId()]
            )
        );

        $child->setInput('Updated input');
        $child->save();
        $this->tester->runCommand('messenger:consume', ['--limit'=>2], ['pimcore_generic_data_index_queue']);

        $response = $this->tester->checkIndexEntry($child->getId(), $indexName);
        $this->assertEquals(
            'Updated input', $response['_source'][FieldCategory::STANDARD_FIELDS->value]['input']
        );
        $this->assertArrayNotHasKey(
            'input',
            $this->getInheritedFieldsResponse($response)
        );
    }

    public function testIndexingWithoutInheritanceSynchronous()
    {
        $object = TestHelper::createEmptyObject();
        $this->assertFalse($object->getClass()->getAllowInherit());
        $indexName = $this->searchIndexConfigService->getIndexName($object->getClassName());

        // check indexed
        $response = $this->tester->checkIndexEntry($object->getId(), $indexName);
        $this->assertEquals($object->getId(), $response['_source']['system_fields']['id']);
        $this->assertEmpty($this->getInheritedFieldsResponse($response));

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
        $this->assertNotEmpty(
            Db::get()->fetchOne(
                'select count(elementId) from generic_data_index_queue where elementId = ? and elementType="dataObject"',
                [$object->getId()]
            )
        );
        $this->tester->runCommand('messenger:consume', ['--limit'=>2], ['pimcore_generic_data_index_queue']);
        $response = $this->tester->checkIndexEntry($object->getId(), $indexName);
        $this->assertEquals($object->getKey(), $response['_source']['system_fields']['key']);
    }

    public function testFolderIndexing()
    {
        $object = TestHelper::createObjectFolder();
        $indexName = $this->searchIndexConfigService->getIndexName(IndexName::DATA_OBJECT_FOLDER->value);

        // check indexed
        $response = $this->tester->checkIndexEntry($object->getId(), $indexName);
        $this->assertEquals($object->getId(), $response['_source']['system_fields']['id']);

        $object->setKey('my-test-folder');
        $object->save();

        $indexName = $this->searchIndexConfigService->getIndexName(IndexName::DATA_OBJECT_FOLDER->value);
        $response = $this->tester->checkIndexEntry($object->getId(), $indexName);
        $this->assertEquals('my-test-folder', $response['_source']['system_fields']['key']);

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
        $object = TestHelper::createEmptyObject('', true, true, MappingTest::class);
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

    public function testClassDefinitionMapping(): void
    {
        $object = TestHelper::createEmptyObject('', true, true, MappingTest::class);
        $index = $this->searchIndexConfigService->getIndexName($object->getClassName());
        $class = $object->getClass();
        $originalFields = $class->getFieldDefinitions();

        $input = new Input();
        $input->setName('mappingTest');
        $class->addFieldDefinition('mappingTest', $input);
        $class->save();
        $this->tester->runCommand('messenger:consume', ['--limit'=>2], ['pimcore_generic_data_index_queue']);

        $indexName = $this->tester->getIndexName($object->getClassName());
        $mapping = $this->tester->getIndexMapping($index);
        $standardFields = $mapping[$indexName]['mappings']['properties']['standard_fields']['properties'];
        $this->assertArrayHasKey('mappingTest', $standardFields);

        $class->setFieldDefinitions($originalFields);
        $class->save();
        $this->tester->runCommand('messenger:consume', ['--limit'=>2], ['pimcore_generic_data_index_queue']);

        $indexName = $this->tester->getIndexName($object->getClassName());
        $mapping = $this->tester->getIndexMapping($index);
        $standardFields = $mapping[$indexName]['mappings']['properties']['standard_fields']['properties'];
        $this->assertArrayNotHasKey('mappingTest', $standardFields);
    }

    public function testClassDefinitionIconChange(): void
    {
        $object = TestHelper::createEmptyObject();

        $this->classDefinitionIconChangeTest($object, '/my-icon.svg');
        $this->classDefinitionIconChangeTest($object, '/my-new-icon.svg');
        $this->classDefinitionIconChangeTest($object, null);
        $this->classDefinitionIconChangeTest($object, '/my-final-icon.svg');
    }

    private function classDefinitionIconChangeTest(Concrete $object, ?string $newIcon): void
    {
        $class = $object->getClass();
        $class->setIcon($newIcon);
        $class->save();

        $this->tester->runCommand('messenger:consume', ['--limit'=>1], ['pimcore_generic_data_index_queue']);

        $indexName = $this->tester->getIndexName($object->getClassName());
        $response = $this->tester->checkIndexEntry($object->getId(), $indexName);
        $updatedIcon = $response['_source']['system_fields']['classDefinitionIcon'];

        $this->assertEquals($newIcon, $updatedIcon);
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

    private function createChildObject(Concrete $parent): Concrete
    {
        $child = $this->createObjectWithInheritance();
        $child->setParentId($parent->getId());
        $child->save();

        return $child;
    }

    private function getInheritedFieldsResponse(array $data): array
    {
        return $data['_source'][FieldCategory::STANDARD_FIELDS->value][FieldCategory::INHERITED_FIELDS->value];
    }
}
