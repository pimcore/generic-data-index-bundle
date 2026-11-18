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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DataObject\FieldDefinitionAdapter;

use Codeception\Test\Unit;
use Exception;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\ClassificationStoreAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\ServiceResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\Checkbox;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Psr\Log\AbstractLogger;
use ReflectionMethod;

/**
 * @internal
 */
final class ClassificationStoreAdapterTest extends Unit
{
    public function testExceptionIsThrownWhenWrongFieldDefinition()
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);

        $adapter = new ClassificationStoreAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );

        $relation = new Checkbox();
        $adapter->setFieldDefinition($relation);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field definition must be an instance of ' . Classificationstore::class);
        $adapter->getIndexMapping();
    }

    public function testWarningIncludesTypeKeyIdGroupIdAndExceptionMessageWhenFieldDefinitionCannotBeResolved(): void
    {
        $adapter = new ClassificationStoreAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class)
        );

        $resolverException = new Exception('unknown implementation for type input');
        $adapter->setClassificationService($this->makeEmpty(ServiceResolverInterface::class, [
            'getFieldDefinitionFromKeyConfig' => static function () use ($resolverException): never {
                throw $resolverException;
            },
        ]));

        $logger = $this->createCollectingLogger();
        $adapter->setLogger($logger);

        $key = new KeyGroupRelation();
        $key->setType('input');
        $key->setGroupId(9);
        $key->setKeyId(42);

        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'getFieldDefinitionForKey');
        $result = $method->invoke($adapter, $key);

        $this->assertNull($result);
        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
        $this->assertSame(
            'Could not get field definition for type input for key 42 in group 9: unknown implementation for type input',
            $logger->records[0]['message']
        );
    }

    /**
     * Regression test for pimcore/platform-version#703: a classification store group that holds no
     * key produced an inheritance entry without a "keys" list, while getInheritedData() reads
     * $group['keys'] for every entry. The resulting "Undefined array key" is thrown as an
     * ErrorException while debug is on, which DataObjectNormalizer turns into a
     * DataObjectNormalizerException - the data object then fails to normalize at all, reported as
     * "Error transforming data object to search result item" on the Studio data object endpoint.
     */
    public function testInheritanceEntryOfAGroupWithoutKeysStillCarriesAKeyList(): void
    {
        $adapter = $this->createAdapter();
        $adapter->setLogger($this->createCollectingLogger());

        $entry = $this->createInheritanceGroupEntry($adapter, $this->group(255, 'T-Shirt'), []);

        $this->assertSame(['name' => 'T-Shirt', 'keys' => []], $entry);
    }

    /**
     * Same shape, reached the other way: the group has keys, but none of them resolves to a field
     * definition, so every key is skipped and the entry is built from no key at all.
     */
    public function testInheritanceEntryCarriesAKeyListWhenNoKeyDefinitionResolves(): void
    {
        $adapter = $this->createAdapter();
        $adapter->setLogger($this->createCollectingLogger());
        $adapter->setClassificationService($this->makeEmpty(ServiceResolverInterface::class, [
            'getFieldDefinitionFromKeyConfig' => static function (): never {
                throw new Exception('unknown implementation for type input');
            },
        ]));

        $entry = $this->createInheritanceGroupEntry(
            $adapter,
            $this->group(255, 'T-Shirt'),
            [$this->key(42, 255, 'Size')]
        );

        $this->assertSame(['name' => 'T-Shirt', 'keys' => []], $entry);
    }

    public function testInheritanceEntryIndexesResolvedKeysByKeyId(): void
    {
        $definition = new Checkbox();

        $adapter = $this->createAdapter();
        $adapter->setLogger($this->createCollectingLogger());
        $adapter->setClassificationService($this->makeEmpty(ServiceResolverInterface::class, [
            'getFieldDefinitionFromKeyConfig' => $definition,
        ]));

        $entry = $this->createInheritanceGroupEntry(
            $adapter,
            $this->group(255, 'T-Shirt'),
            [$this->key(42, 255, 'Size')]
        );

        $this->assertSame(
            [
                'name' => 'T-Shirt',
                'keys' => [42 => ['name' => 'Size', 'definition' => $definition]],
            ],
            $entry
        );
    }

    /**
     * @param KeyGroupRelation[] $keys
     */
    private function createInheritanceGroupEntry(
        ClassificationStoreAdapter $adapter,
        GroupConfig $group,
        array $keys
    ): array {
        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'createInheritanceGroupEntry');

        return $method->invoke($adapter, $group, $keys);
    }

    private function group(int $id, string $name): GroupConfig
    {
        $group = new GroupConfig();
        $group->setId($id);
        $group->setName($name);

        return $group;
    }

    private function key(int $keyId, int $groupId, string $name): KeyGroupRelation
    {
        $key = new KeyGroupRelation();
        $key->setKeyId($keyId);
        $key->setGroupId($groupId);
        $key->setName($name);
        $key->setType('input');

        return $key;
    }

    private function createAdapter(): ClassificationStoreAdapter
    {
        return new ClassificationStoreAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class)
        );
    }

    private function createCollectingLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var array<int, array{level: string, message: string}> */
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = ['level' => (string) $level, 'message' => (string) $message];
            }
        };
    }
}
