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

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DataObjectTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\Element\Tag;
use Pimcore\Model\User;
use Pimcore\Tests\Support\Util\TestHelper;

class TagIndexingTest extends Unit
{
    protected IndexTester $tester;

    private Folder $folder1;

    private Tag $tagElement;

    private array $objects;

    private array $tags;

    private DataObjectTypeAdapter $dataObjectTypeAdapter;

    protected function _before(): void
    {
        $this->dataObjectTypeAdapter = $this->tester->grabService(DataObjectTypeAdapter::class);
        $this->user = new User();
        $this->tag = new Tag();
        $this->tester->enableSynchronousProcessing();
        $this->prepareElements();
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    public function testAssignTagsToElement(): void
    {
        /** @var DataObject $object */
        $object = $this->objects[0];
        $this->tag::addTagToElement($object->getType(), $object->getId(), $this->tags[0]);
        $this->tag::addTagToElement($object->getType(), $object->getId(), $this->tags[1]);

        $indexName = $this->dataObjectTypeAdapter->getAliasIndexName($object->getClass());
        $response = $this->tester->checkIndexEntry($object->getId(), $indexName);
        $tags = $this->getTagsFormResponse($response);

        $this->assertNotEmpty($tags);
        $this->assertCount(2, $tags);
        $this->assertSame($this->tags[0]->getId(), $tags[0]);
        $this->assertSame($this->tags[1]->getId(), $tags[1]);
    }

    public function testRemoveTagsFromElement(): void
    {
        $this->testAssignTagsToElement();

        /** @var DataObject $object */
        $object = $this->objects[0];
        $this->tag::removeTagFromElement($object->getType(), $object->getId(), $this->tags[0]);
        $this->tag::removeTagFromElement($object->getType(), $object->getId(), $this->tags[1]);

        $indexName = $this->dataObjectTypeAdapter->getAliasIndexName($object->getClass());
        $response = $this->tester->checkIndexEntry($object->getId(), $indexName);
        $tags = $this->getTagsFormResponse($response);

        $this->assertEmpty($tags);
    }

    public function testBatchAssignTagsToElements(): void
    {
        $this->testAssignTagsToElement();

        /** @var DataObject $object */
        $object1 = $this->objects[0];

        /** @var DataObject $object */
        $object2 = $this->objects[1];

        $this->tag::batchAssignTagsToElement(
            $object1->getType(),
            [$object1->getId(), $object2->getId()],
            [$this->tags[2]->getId()]
        );
        $indexName = $this->dataObjectTypeAdapter->getAliasIndexName($object1->getClass());

        $response = $this->tester->checkIndexEntry($object1->getId(), $indexName);
        $tags = $this->getTagsFormResponse($response);

        // testAssignTagsToElement should add tags[0] and tags[1]
        $this->assertCount(3, $tags);
        $this->assertSame(
            [
                $this->tags[0]->getId(),
                $this->tags[1]->getId(),
                $this->tags[2]->getId(),
            ],
            $tags
        );

        $response = $this->tester->checkIndexEntry($object2->getId(), $indexName);
        $tags = $this->getTagsFormResponse($response);

        $this->assertCount(1, $tags);
        $this->assertSame([$this->tags[2]->getId()], $tags);
    }

    public function testBatchReplaceTags(): void
    {
        $this->testAssignTagsToElement();

        /** @var DataObject $object */
        $object1 = $this->objects[0];

        /** @var DataObject $object */
        $object2 = $this->objects[1];

        $this->tag::batchAssignTagsToElement(
            $object1->getType(),
            [$object1->getId(), $object2->getId()],
            [$this->tags[2]->getId()],
            true
        );
        $indexName = $this->dataObjectTypeAdapter->getAliasIndexName($object1->getClass());

        $response = $this->tester->checkIndexEntry($object1->getId(), $indexName);
        $tags = $this->getTagsFormResponse($response);

        // testAssignTagsToElement should add tags[0] and tags[1] but they are replaced now
        $this->assertCount(1, $tags);
        $this->assertSame([$this->tags[2]->getId()], $tags);

        $response = $this->tester->checkIndexEntry($object2->getId(), $indexName);
        $tags = $this->getTagsFormResponse($response);

        $this->assertCount(1, $tags);
        $this->assertSame([$this->tags[2]->getId()], $tags);
    }

    private function prepareElements(): void
    {
        $folder1 = TestHelper::createObjectFolder();
        $objects = TestHelper::createEmptyObjects(count: 2);
        foreach ($objects as $key => $object) {
            $parentId = $key === 0 ? $folder1->getId() : $objects[$key-1]->getId();
            $object->setParentId($parentId);
            $object->save();
        }
        $folder1
            ->setKey('test-folder')
            ->setLocked('propagate')
            ->save();

        for ($i = 0; $i < 3; $i++) {
            $this->tags[] = TestHelper::createTag(sprintf('Tag_%d', $i));
        }

        $this->folder1 = $folder1;
        $this->objects = $objects;
    }

    private function getTagsFormResponse(array $response): array
    {
        return $response['_source']['system_fields']['tags'] ?? [];
    }
}
