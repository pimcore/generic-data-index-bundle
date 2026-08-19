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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\TextKeywordAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\Concrete;

/**
 * Inheritance origin resolution walks the parent chain per empty attribute and
 * language. On variant-heavy datasets thousands of siblings share one parent, so
 * the walk result must be memoized per (parent, key, language) — request-scoped,
 * so LongRunningHelper::cleanUp() between batches bounds it.
 *
 * @internal
 */
final class AbstractAdapterInheritanceTest extends Unit
{
    public function testParentValueIsResolvedOnceForSiblingsSharingTheParent(): void
    {
        $parent = $this->createObject(10, get: Expected::once('inherited value'));

        $adapter = $this->createAdapter();

        $expected = ['title' => ['originId' => 10]];
        $this->assertSame(
            $expected,
            $adapter->getInheritedData($this->createObject(1, parent: $parent), 1, '', 'title')
        );
        $this->assertSame(
            $expected,
            $adapter->getInheritedData($this->createObject(2, parent: $parent), 2, '', 'title')
        );
    }

    public function testSharedAncestorChainIsWalkedOnce(): void
    {
        $grandParent = $this->createObject(20, get: Expected::once('inherited value'));
        $parent = $this->createObject(10, parent: $grandParent, get: Expected::once(''));

        $adapter = $this->createAdapter();

        $expected = ['title' => ['originId' => 20]];
        $this->assertSame(
            $expected,
            $adapter->getInheritedData($this->createObject(1, parent: $parent), 1, '', 'title')
        );
        $this->assertSame(
            $expected,
            $adapter->getInheritedData($this->createObject(2, parent: $parent), 2, '', 'title')
        );
    }

    public function testDistinctLanguagesDoNotShareTheMemo(): void
    {
        $parent = $this->createObject(10, get: Expected::exactly(2, 'inherited value'));

        $adapter = $this->createAdapter();

        $variant = $this->createObject(1, parent: $parent);

        $this->assertSame(
            ['title.en' => ['originId' => 10]],
            $adapter->getInheritedData($variant, 1, '', 'title', 'en')
        );
        $this->assertSame(
            ['title.de' => ['originId' => 10]],
            $adapter->getInheritedData($variant, 1, '', 'title', 'de')
        );
    }

    public function testEmptyWholeChainAttributesTheTopmostAncestor(): void
    {
        $parent = $this->createObject(10, get: Expected::once(''));

        $adapter = $this->createAdapter();

        $this->assertSame(
            ['title' => ['originId' => 10]],
            $adapter->getInheritedData($this->createObject(1, parent: $parent), 1, '', 'title')
        );
    }

    public function testNonEmptyOwnValueSkipsTheParentWalk(): void
    {
        $parent = $this->createObject(10, get: Expected::never());

        $adapter = $this->createAdapter();

        $this->assertSame(
            [],
            $adapter->getInheritedData($this->createObject(1, parent: $parent), 1, 'own value', 'title')
        );
    }

    private function createAdapter(): TextKeywordAdapter
    {
        $adapter = new TextKeywordAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class),
            $this->makeEmpty(IndexMappingServiceInterface::class),
        );
        $adapter->setFieldDefinition((new Input())->setName('title'));
        $adapter->setRuntimeCacheResolver($this->createArrayBackedRuntimeCacheResolver());

        return $adapter;
    }

    private function createArrayBackedRuntimeCacheResolver(): RuntimeCacheResolverInterface
    {
        $store = [];

        return $this->makeEmpty(RuntimeCacheResolverInterface::class, [
            'isRegistered' => function (string $key) use (&$store): bool {
                return array_key_exists($key, $store);
            },
            'load' => function (string $key) use (&$store): mixed {
                return $store[$key] ?? null;
            },
            'save' => function (mixed $data, string $key) use (&$store): void {
                $store[$key] = $data;
            },
        ]);
    }

    private function createObject(int $id, ?Concrete $parent = null, mixed $get = null): Concrete
    {
        return $this->makeEmpty(Concrete::class, [
            'getId' => $id,
            'getModificationDate' => 1000,
            'getNextParentForInheritance' => $parent,
            'get' => $get,
        ]);
    }
}
