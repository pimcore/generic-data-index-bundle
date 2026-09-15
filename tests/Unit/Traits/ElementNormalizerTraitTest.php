<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Traits;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\ElementNormalizerTrait;
use Pimcore\Model\Element\Tag;
use ReflectionProperty;

/**
 * @internal
 */
final class ElementNormalizerTraitTest extends Unit
{
    private object $harness;

    protected function _before(): void
    {
        $this->harness = new class() {
            use ElementNormalizerTrait;

            public function tagIds(array $tags): array
            {
                return $this->extractTagIds($tags);
            }

            public function parentTagIds(array $tags): array
            {
                return $this->extractParentTagIds($tags);
            }
        };
    }

    public function testExtractTagIdsReturnsTheIdsOfTheGivenTags(): void
    {
        $tags = [$this->createTag(11), $this->createTag(12)];

        $this->assertSame([11, 12], $this->harness->tagIds($tags));
    }

    public function testExtractParentTagIdsCollectsTheWholeParentChain(): void
    {
        $grandParent = $this->createTag(1);
        $parent = $this->createTag(2, $grandParent);
        $tag = $this->createTag(3, $parent);

        $this->assertSame([2, 1], $this->harness->parentTagIds([$tag]));
    }

    public function testExtractParentTagIdsDeduplicatesSharedParents(): void
    {
        $parent = $this->createTag(5);
        $tagA = $this->createTag(6, $parent);
        $tagB = $this->createTag(7, $parent);

        $this->assertSame([5], $this->harness->parentTagIds([$tagA, $tagB]));
    }

    public function testExtractParentTagIdsStopsOnACircularParentChain(): void
    {
        $tagA = $this->createTag(8);
        $tagB = $this->createTag(9, $tagA);
        $this->setParent($tagA, $tagB);

        $this->assertSame([8, 9], $this->harness->parentTagIds([$tagB]));
    }

    public function testExtractParentTagIdsIsEmptyForRootTags(): void
    {
        $this->assertSame([], $this->harness->parentTagIds([$this->createTag(10)]));
    }

    private function createTag(int $id, ?Tag $parent = null): Tag
    {
        $tag = new Tag();
        $tag->setId($id);

        if ($parent !== null) {
            $this->setParent($tag, $parent);
        }

        return $tag;
    }

    private function setParent(Tag $tag, Tag $parent): void
    {
        // Tag has no setParent(), and setParentId() walks the parent chain via the
        // database (correctPath()); preset both properties so the parent walk under
        // test stays entirely in memory.
        $parentIdProperty = new ReflectionProperty(Tag::class, 'parentId');
        $parentIdProperty->setValue($tag, $parent->getId());

        $parentProperty = new ReflectionProperty(Tag::class, 'parent');
        $parentProperty->setValue($tag, $parent);
    }
}
