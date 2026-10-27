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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\ClassificationStoreAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\ServiceResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\Checkbox;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Psr\Log\AbstractLogger;
use ReflectionMethod;

/**
 * @internal
 */
final class ClassificationStoreAdapterTest extends Unit
{
    private const KEY_NAME = 'Size';

    private const KEY_MAPPING = ['type' => 'keyword'];

    private const PROPERTIES = 'properties';

    private const LANGUAGE_NODE = [self::PROPERTIES => [self::KEY_NAME => self::KEY_MAPPING]];

    private const DEFAULT_LANGUAGE = 'default';

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
     * Regression test for pimcore/platform-version#271: a classification store key whose name ends
     * with a dot produced the field path "<field>.<group>.<key>..<language>", which the search index
     * rejects with "object field starting or ending with a [.] makes object resolution ambiguous",
     * failing the whole bulk request.
     */
    public function testNameSegmentEndingWithADotIsTrimmed(): void
    {
        $adapter = $this->createAdapter();
        $logger = $this->createCollectingLogger();
        $adapter->setLogger($logger);

        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'normalizeNameSegment');

        $this->assertSame('Size_Drive_Dr', $method->invoke($adapter, 'Size_Drive_Dr.', 'key', 42, true));
        $this->assertCount(1, $logger->records);
        $this->assertSame('info', $logger->records[0]['level']);

        // The document paths run per element and per locale, so they stay silent.
        $method->invoke($adapter, 'Size_Drive_Dr.', 'key', 42);
        $this->assertCount(1, $logger->records, 'only the mapping path may report a normalised name');
    }

    public function testNameSegmentStartingWithADotIsTrimmed(): void
    {
        $adapter = $this->createAdapter();
        $adapter->setLogger($this->createCollectingLogger());

        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'normalizeNameSegment');

        $this->assertSame('Specifications', $method->invoke($adapter, '.Specifications', 'group', 9));
    }

    public function testNameSegmentWithoutLeadingOrTrailingDotIsUntouchedAndNotLogged(): void
    {
        $adapter = $this->createAdapter();
        $logger = $this->createCollectingLogger();
        $adapter->setLogger($logger);

        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'normalizeNameSegment');

        // A single dot inside the name is left alone: it creates a nested sub-object, which the search
        // index accepts, and rewriting it would change field names that index today.
        $this->assertSame(
            'Regular Socket - Spec.ifications',
            $method->invoke($adapter, 'Regular Socket - Spec.ifications', 'group', 9)
        );
        $this->assertSame('Size_Drive_Dr', $method->invoke($adapter, 'Size_Drive_Dr', 'key', 42));
        $this->assertCount(0, $logger->records, 'an unaffected name must not produce log noise');
    }

    /**
     * A doubled dot inside a name adds the same empty path component as a leading or trailing one, and
     * the search index rejects it for the same reason.
     */
    public function testConsecutiveDotsInsideANameAreCollapsed(): void
    {
        $adapter = $this->createAdapter();
        $adapter->setLogger($this->createCollectingLogger());

        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'normalizeNameSegment');

        $this->assertSame('Size.Drive', $method->invoke($adapter, 'Size..Drive', 'key', 42));
        $this->assertSame('Size.Drive', $method->invoke($adapter, '.Size...Drive.', 'key', 42));
    }

    /**
     * Two names that differ only in dot placement normalise to the same segment. Classification store
     * names are not unique, so without a guard the later entry would overwrite the earlier one.
     */
    public function testCollidingNormalizedKeyNameIsSkippedRatherThanOverwritingTheMapping(): void
    {
        $adapter = $this->createAdapter();
        $logger = $this->createCollectingLogger();
        $adapter->setLogger($logger);

        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'isTakenNameSegment');

        $this->assertFalse($method->invoke($adapter, [], 'Size', 'Size', 'key', 42, true));
        $this->assertCount(0, $logger->records);

        $this->assertTrue($method->invoke($adapter, ['Size' => []], 'Size', 'Size.', 'key', 43, true));
        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
        $this->assertStringContainsString('already', $logger->records[0]['message']);

        // The document paths stay silent for the same reason as normalizeNameSegment().
        $this->assertTrue($method->invoke($adapter, ['Size' => []], 'Size', 'Size.', 'key', 43));
        $this->assertCount(1, $logger->records);
    }

    public function testNameSegmentOfOnlyDotsIsSkippedWithAWarning(): void
    {
        $adapter = $this->createAdapter();
        $logger = $this->createCollectingLogger();
        $adapter->setLogger($logger);

        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'normalizeNameSegment');

        $this->assertNull($method->invoke($adapter, '..', 'key', 42, true));
        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
        $this->assertStringContainsString('consists only of dots', $logger->records[0]['message']);
    }

    /**
     * Regression test for pimcore/platform-version#504: every group of the store was mapped, times every
     * key, so a large store exceeded the index total fields limit although the class field only allows a
     * few of its groups. Allowed group ids are stored as strings when entered in the class editor.
     */
    public function testOnlyAllowedGroupsAreMapped(): void
    {
        $fieldDefinition = new Classificationstore();
        $fieldDefinition->setAllowedGroupIds('12, 13');

        $this->assertSame([12, 13], $this->getMappedGroupIds($fieldDefinition, [11, 12, 13, 14]));
    }

    public function testAllGroupsAreMappedWhenNoGroupsAreAllowedExplicitly(): void
    {
        $fieldDefinition = new Classificationstore();
        $fieldDefinition->setAllowedGroupIds([]);

        $this->assertSame([11, 12], $this->getMappedGroupIds($fieldDefinition, [11, 12]));
    }

    /**
     * Class definitions that never set the allowed groups leave the typed property uninitialized.
     */
    public function testAllGroupsAreMappedWhenAllowedGroupsWereNeverSet(): void
    {
        $this->assertSame([11, 12], $this->getMappedGroupIds(new Classificationstore(), [11, 12]));
    }

    /**
     * The document writes <group>.<language>.<key> for every valid language, so the mapping has to
     * declare the same language nodes - otherwise they are only mapped dynamically, or, under
     * "dynamic: false", not at all.
     */
    public function testEveryValidLanguageIsDeclaredForALocalizedStore(): void
    {
        $fieldDefinition = new Classificationstore();
        $fieldDefinition->setLocalized(true);

        $mapping = $this->getGroupMapping($fieldDefinition, ['de', 'en']);

        $this->assertSame([self::DEFAULT_LANGUAGE, 'de', 'en'], array_keys($mapping));
        foreach ($mapping as $language => $languageNode) {
            $this->assertSame(self::LANGUAGE_NODE, $languageNode, $language);
        }
    }

    public function testOnlyTheDefaultNodeIsDeclaredForANonLocalizedStore(): void
    {
        $fieldDefinition = new Classificationstore();
        $fieldDefinition->setLocalized(false);

        $mapping = $this->getGroupMapping($fieldDefinition, ['de', 'en', 'fr']);

        $this->assertSame([self::DEFAULT_LANGUAGE], array_keys($mapping));
        $this->assertSame(self::LANGUAGE_NODE, $mapping[self::DEFAULT_LANGUAGE]);
    }

    /**
     * A group, language or key that is not declared stays in _source without being indexed, instead of
     * being auto-mapped with a guessed type that the next mapping update then contradicts.
     */
    public function testNestedRootDeclaresDynamicFalse(): void
    {
        $groupMapping = [
            'Specifications' => [self::PROPERTIES => [self::DEFAULT_LANGUAGE => self::LANGUAGE_NODE]],
        ];

        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'getNestedRootMapping');

        $this->assertSame(
            [
                'type' => AttributeType::NESTED,
                'dynamic' => false,
                self::PROPERTIES => $groupMapping,
            ],
            $method->invoke($this->createAdapter(), $groupMapping)
        );
    }

    /**
     * The key mapping is built once per group and replicated per language, so the diagnostics of a key
     * name do not repeat for every language of the store.
     */
    public function testKeyDiagnosticsAreReportedOncePerKeyRegardlessOfLanguageCount(): void
    {
        $fieldDefinition = new Classificationstore();
        $fieldDefinition->setLocalized(true);
        $logger = $this->createCollectingLogger();

        $mapping = $this->getGroupMapping($fieldDefinition, ['de', 'en', 'fr'], self::KEY_NAME . '.', $logger);

        $this->assertSame([self::DEFAULT_LANGUAGE, 'de', 'en', 'fr'], array_keys($mapping));
        $this->assertCount(1, $logger->records);
        $this->assertSame('info', $logger->records[0]['level']);
    }

    private function getGroupMapping(
        Classificationstore $fieldDefinition,
        array $validLanguages,
        string $keyName = self::KEY_NAME,
        ?AbstractLogger $logger = null
    ): array {
        $adapter = new ClassificationStoreAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class, [
                'getFieldDefinitionAdapter' => $this->makeEmpty(AdapterInterface::class, [
                    'getIndexMapping' => self::KEY_MAPPING,
                ]),
            ])
        );
        $adapter->setClassificationService($this->makeEmpty(ServiceResolverInterface::class, [
            'getFieldDefinitionFromKeyConfig' => new Input(),
        ]));
        $adapter->setLanguageService($this->makeEmpty(LanguageServiceInterface::class, [
            'getValidLanguages' => $validLanguages,
        ]));
        $adapter->setLogger($logger ?? $this->createCollectingLogger());

        $key = new KeyGroupRelation();
        $key->setKeyId(42);
        $key->setName($keyName);

        $languages = (new ReflectionMethod(ClassificationStoreAdapter::class, 'getValidLanguages'))
            ->invoke($adapter, $fieldDefinition);

        return (new ReflectionMethod(ClassificationStoreAdapter::class, 'getMappingForGroupConfig'))
            ->invoke($adapter, [$key], $languages);
    }

    private function getMappedGroupIds(Classificationstore $fieldDefinition, array $groupIds): array
    {
        $groups = array_map(
            static fn (int $groupId): GroupConfig => (new GroupConfig())->setId($groupId)->setName('g' . $groupId),
            $groupIds
        );

        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'filterAllowedGroups');
        $result = $method->invoke($this->createAdapter(), $groups, $fieldDefinition);

        return array_values(array_map(static fn (GroupConfig $group): int => $group->getId(), $result));
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
