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
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Checkbox;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Classificationstore\DefinitionCache;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Psr\Log\AbstractLogger;
use ReflectionMethod;

/**
 * @internal
 */
final class ClassificationStoreAdapterTest extends Unit
{
    private const KEY_NAME = 'Size';

    private const OTHER_KEY_NAME = 'Weight';

    private const THIRD_KEY_NAME = 'Colour';

    private const KEY_ID = 42;

    private const OTHER_KEY_ID = 43;

    private const THIRD_KEY_ID = 44;

    private const GROUP_ID = 9;

    private const OTHER_GROUP_ID = 10;

    private const METADATA_INDEXED = 'indexed';

    private const KEY_MAPPING = ['type' => 'keyword'];

    private const PROPERTIES = 'properties';

    private const LANGUAGE_NODE = [self::PROPERTIES => [self::KEY_NAME => self::KEY_MAPPING]];

    private const DEFAULT_LANGUAGE = 'default';

    protected function _after(): void
    {
        DefinitionCache::clear(null);
    }

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

    /**
     * A key marked as not indexed on its assignment to the group contributes no mapping property, under
     * none of the declared language nodes, while every other key of the group is mapped as before.
     */
    public function testKeyExcludedOnItsAssignmentContributesNoMappingProperty(): void
    {
        $fieldDefinition = new Classificationstore();
        $fieldDefinition->setLocalized(true);

        $excluded = $this->createRelation(self::OTHER_KEY_ID, self::GROUP_ID, self::OTHER_KEY_NAME);
        $excluded->setMetadata([self::METADATA_INDEXED => false]);

        $mapping = $this->getMappingForRelations(
            $fieldDefinition,
            ['de', 'en'],
            [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME), $excluded]
        );

        $this->assertSame([self::DEFAULT_LANGUAGE, 'de', 'en'], array_keys($mapping));
        foreach ($mapping as $language => $languageNode) {
            $this->assertSame(self::LANGUAGE_NODE, $languageNode, $language);
        }
    }

    /**
     * The exclusion reaches the document as well, so the value of an excluded key is in neither the
     * indexed document nor _source, while the other keys of the same group keep theirs.
     */
    public function testKeyExcludedOnItsAssignmentContributesNoDocumentValue(): void
    {
        $excluded = $this->createRelation(self::OTHER_KEY_ID, self::GROUP_ID, self::OTHER_KEY_NAME);
        $excluded->setMetadata([self::METADATA_INDEXED => false]);

        $values = $this->getDocumentValues(
            [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME), $excluded],
            [self::DEFAULT_LANGUAGE, 'de']
        );

        $this->assertSame(
            [
                self::DEFAULT_LANGUAGE => [self::KEY_NAME => self::KEY_ID],
                'de' => [self::KEY_NAME => self::KEY_ID],
            ],
            $values
        );
    }

    /**
     * The flag belongs to the assignment, not to the key: the same key id is mapped and written under the
     * group whose assignment does not carry it, and under neither in the group whose assignment does.
     */
    public function testTheSameKeyIsIndexedInOneGroupAndExcludedInAnother(): void
    {
        $fieldDefinition = new Classificationstore();
        $fieldDefinition->setLocalized(false);

        $indexedAssignment = $this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME);
        $excludedAssignment = $this->createRelation(self::KEY_ID, self::OTHER_GROUP_ID, self::KEY_NAME);
        $excludedAssignment->setMetadata([self::METADATA_INDEXED => false]);

        $this->assertSame(
            [self::DEFAULT_LANGUAGE => self::LANGUAGE_NODE],
            $this->getMappingForRelations($fieldDefinition, [], [$indexedAssignment])
        );
        $this->assertSame([], $this->getMappingForRelations($fieldDefinition, [], [$excludedAssignment]));

        $this->assertSame(
            [self::DEFAULT_LANGUAGE => [self::KEY_NAME => self::KEY_ID]],
            $this->getDocumentValues([$indexedAssignment], [self::DEFAULT_LANGUAGE], self::GROUP_ID)
        );
        $this->assertSame(
            [],
            $this->getDocumentValues([$excludedAssignment], [self::DEFAULT_LANGUAGE], self::OTHER_GROUP_ID)
        );
    }

    /**
     * Nothing is excluded unless the flag says so: an assignment without metadata, one with metadata that
     * carries no index flag and one with the flag set to indexed are all mapped and written as before.
     */
    public function testRelationWithoutIndexFlagIsIndexedAsBefore(): void
    {
        $fieldDefinition = new Classificationstore();
        $fieldDefinition->setLocalized(false);

        $withoutMetadata = $this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME);

        $withoutFlag = $this->createRelation(self::OTHER_KEY_ID, self::GROUP_ID, self::OTHER_KEY_NAME);
        $withoutFlag->setMetadata(['subGroup' => 'Dimensions']);

        $flaggedAsIndexed = $this->createRelation(self::THIRD_KEY_ID, self::GROUP_ID, self::THIRD_KEY_NAME);
        $flaggedAsIndexed->setMetadata([self::METADATA_INDEXED => true]);

        $relations = [$withoutMetadata, $withoutFlag, $flaggedAsIndexed];

        $this->assertSame(
            [
                self::DEFAULT_LANGUAGE => [
                    self::PROPERTIES => [
                        self::KEY_NAME => self::KEY_MAPPING,
                        self::OTHER_KEY_NAME => self::KEY_MAPPING,
                        self::THIRD_KEY_NAME => self::KEY_MAPPING,
                    ],
                ],
            ],
            $this->getMappingForRelations($fieldDefinition, [], $relations)
        );

        $this->assertSame(
            [
                self::DEFAULT_LANGUAGE => [
                    self::KEY_NAME => self::KEY_ID,
                    self::OTHER_KEY_NAME => self::OTHER_KEY_ID,
                    self::THIRD_KEY_NAME => self::THIRD_KEY_ID,
                ],
            ],
            $this->getDocumentValues($relations, [self::DEFAULT_LANGUAGE])
        );
    }

    /**
     * The excluded assignment is dropped before its name is normalized, so it does not reserve the segment
     * "Size" that the key named "Size." normalizes to - on the mapping and on the document path alike.
     */
    public function testExcludedKeyDoesNotReserveItsNormalizedNameSegment(): void
    {
        $fieldDefinition = new Classificationstore();
        $fieldDefinition->setLocalized(false);

        $excluded = $this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME);
        $excluded->setMetadata([self::METADATA_INDEXED => false]);
        $indexed = $this->createRelation(self::OTHER_KEY_ID, self::GROUP_ID, self::KEY_NAME . '.');

        $this->assertSame(
            [self::DEFAULT_LANGUAGE => self::LANGUAGE_NODE],
            $this->getMappingForRelations($fieldDefinition, [], [$excluded, $indexed])
        );

        // the stubbed value is the key id, so it names the assignment the mapped "Size" was written from
        $this->assertSame(
            [self::DEFAULT_LANGUAGE => [self::KEY_NAME => self::OTHER_KEY_ID]],
            $this->getDocumentValues([$excluded, $indexed], [self::DEFAULT_LANGUAGE])
        );
    }

    /**
     * The third path the invariant covers: an excluded assignment emits no inherited field path either, so
     * the mapping, the document and the inheritance path keep dropping the same assignments.
     */
    public function testExcludedKeyProducesNoInheritedFieldPath(): void
    {
        $excluded = $this->createRelation(self::OTHER_KEY_ID, self::GROUP_ID, self::OTHER_KEY_NAME);
        $excluded->setMetadata([self::METADATA_INDEXED => false]);

        $keyMapping = (new ReflectionMethod(ClassificationStoreAdapter::class, 'getKeyMappingForInheritance'))
            ->invoke(
                $this->createConfiguredAdapter([]),
                [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME), $excluded]
            );

        $this->assertSame([self::KEY_ID], array_keys($keyMapping));
        $this->assertSame(self::KEY_NAME, $keyMapping[self::KEY_ID]['name']);
    }

    private function getGroupMapping(
        Classificationstore $fieldDefinition,
        array $validLanguages,
        string $keyName = self::KEY_NAME,
        ?AbstractLogger $logger = null
    ): array {
        return $this->getMappingForRelations(
            $fieldDefinition,
            $validLanguages,
            [$this->createRelation(self::KEY_ID, self::GROUP_ID, $keyName)],
            $logger
        );
    }

    /**
     * @param KeyGroupRelation[] $relations
     */
    private function getMappingForRelations(
        Classificationstore $fieldDefinition,
        array $validLanguages,
        array $relations,
        ?AbstractLogger $logger = null
    ): array {
        $adapter = $this->createConfiguredAdapter($validLanguages, $logger);

        $languages = (new ReflectionMethod(ClassificationStoreAdapter::class, 'getValidLanguages'))
            ->invoke($adapter, $fieldDefinition);

        return (new ReflectionMethod(ClassificationStoreAdapter::class, 'getMappingForGroupConfig'))
            ->invoke($adapter, $relations, $languages);
    }

    /**
     * The store is stubbed to return the key id as the value of every key, so a document entry names the
     * assignment it was written from. The definition cache is seeded for the same reason the store is
     * stubbed: the value path would otherwise read the key configurations from the database.
     *
     * @param KeyGroupRelation[] $relations
     * @param string[] $validLanguages
     */
    private function getDocumentValues(
        array $relations,
        array $validLanguages,
        int $groupId = self::GROUP_ID
    ): array {
        foreach ($relations as $relation) {
            DefinitionCache::put((new KeyConfig())->setId($relation->getKeyId()));
        }

        $store = $this->makeEmpty(ClassificationstoreModel::class, [
            'getLocalizedKeyValue' => static fn (int $group, int $keyId): int => $keyId,
        ]);

        return (new ReflectionMethod(ClassificationStoreAdapter::class, 'normalizeGroupValues'))
            ->invoke($this->createConfiguredAdapter($validLanguages), $store, $groupId, $relations, $validLanguages);
    }

    private function createRelation(int $keyId, int $groupId, string $name): KeyGroupRelation
    {
        $relation = new KeyGroupRelation();
        $relation->setKeyId($keyId);
        $relation->setGroupId($groupId);
        $relation->setName($name);

        return $relation;
    }

    /**
     * @param string[] $validLanguages
     */
    private function createConfiguredAdapter(
        array $validLanguages,
        ?AbstractLogger $logger = null
    ): ClassificationStoreAdapter {
        $adapter = new ClassificationStoreAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class, [
                'getFieldDefinitionAdapter' => $this->makeEmpty(AdapterInterface::class, [
                    'getIndexMapping' => self::KEY_MAPPING,
                ]),
                'normalizeValue' => static fn (?Data $fieldDefinition, mixed $value): mixed => $value,
            ])
        );
        $adapter->setClassificationService($this->makeEmpty(ServiceResolverInterface::class, [
            'getFieldDefinitionFromKeyConfig' => new Input(),
        ]));
        $adapter->setLanguageService($this->makeEmpty(LanguageServiceInterface::class, [
            'getValidLanguages' => $validLanguages,
        ]));
        $adapter->setLogger($logger ?? $this->createCollectingLogger());

        return $adapter;
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
