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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\SearchIndex;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DataObjectTypeAdapter;
use Pimcore\Model\DataObject\Unittest;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * Tests that unpublished data objects are correctly indexed in relation fields.
 *
 * @see https://github.com/pimcore/generic-data-index-bundle/issues/424
 */
class UnpublishedRelationIndexingTest extends \Codeception\Test\Unit
{
    /**
     * @var \Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester
     */
    protected $tester;

    private DataObjectTypeAdapter $dataObjectTypeAdapter;

    protected function _before()
    {
        $this->dataObjectTypeAdapter = $this->tester->grabService(DataObjectTypeAdapter::class);
        $this->tester->enableSynchronousProcessing();
        $this->tester->enableSynchronousProcessingRelatedIds();
        $this->tester->clearQueue();
    }

    protected function _after()
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    public function testUnpublishedObjectIsIndexedInManyToManyRelation(): void
    {
        /** @var Unittest $unpublishedObject */
        $unpublishedObject = TestHelper::createEmptyObject('', true, false);
        $this->assertFalse($unpublishedObject->getPublished());

        /** @var Unittest $publishedObject */
        $publishedObject = TestHelper::createEmptyObject();
        $publishedObject->setObjects([$unpublishedObject])->save();

        $indexName = $this->dataObjectTypeAdapter->getAliasIndexName($publishedObject->getClass());
        $response = $this->tester->checkIndexEntry($publishedObject->getId(), $indexName);

        $standardFields = $response['_source'][FieldCategory::STANDARD_FIELDS->value];
        $this->assertArrayHasKey('objects', $standardFields, 'objects relation field should exist in index');

        $relationData = $standardFields['objects'];
        $this->assertContains(
            $unpublishedObject->getId(),
            $relationData['object'],
            'Unpublished object ID should be present in the relation field index data'
        );
    }

    public function testRelationIndexesBothPublishedAndUnpublishedObjects(): void
    {
        /** @var Unittest $unpublishedObject */
        $unpublishedObject = TestHelper::createEmptyObject('', true, false);
        $this->assertFalse($unpublishedObject->getPublished());

        /** @var Unittest $publishedRelated */
        $publishedRelated = TestHelper::createEmptyObject();
        $this->assertTrue($publishedRelated->getPublished());

        /** @var Unittest $objectWithRelations */
        $objectWithRelations = TestHelper::createEmptyObject();
        $objectWithRelations->setObjects([$unpublishedObject, $publishedRelated])->save();

        $indexName = $this->dataObjectTypeAdapter->getAliasIndexName($objectWithRelations->getClass());
        $response = $this->tester->checkIndexEntry($objectWithRelations->getId(), $indexName);

        $standardFields = $response['_source'][FieldCategory::STANDARD_FIELDS->value];
        $relationData = $standardFields['objects'];

        $this->assertContains(
            $unpublishedObject->getId(),
            $relationData['object'],
            'Unpublished object ID should be in the relation index data'
        );
        $this->assertContains(
            $publishedRelated->getId(),
            $relationData['object'],
            'Published object ID should be in the relation index data'
        );
        $this->assertCount(
            2,
            $relationData['object'],
            'Both published and unpublished objects should be indexed in relation'
        );
    }

    public function testUnpublishedObjectIsInBothDependenciesAndRelations(): void
    {
        /** @var Unittest $unpublishedObject */
        $unpublishedObject = TestHelper::createEmptyObject('', true, false);

        /** @var Unittest $objectWithRelation */
        $objectWithRelation = TestHelper::createEmptyObject();
        $objectWithRelation->setObjects([$unpublishedObject])->save();

        $indexName = $this->dataObjectTypeAdapter->getAliasIndexName($objectWithRelation->getClass());
        $response = $this->tester->checkIndexEntry($objectWithRelation->getId(), $indexName);

        // Check relation field
        $standardFields = $response['_source'][FieldCategory::STANDARD_FIELDS->value];
        $relationData = $standardFields['objects'];
        $this->assertContains(
            $unpublishedObject->getId(),
            $relationData['object'],
            'Unpublished object should be in relation field'
        );

        $systemFields = $response['_source']['system_fields'];
        $dependencies = $systemFields['dependencies'];
        $dependencyObjectIds = $dependencies['object'] ?? [];
        $this->assertContains(
            $unpublishedObject->getId(),
            $dependencyObjectIds,
            'Unpublished object should be in dependencies'
        );
    }
}
