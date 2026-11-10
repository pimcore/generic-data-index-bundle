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

use ArrayObject;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use Exception;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ClientType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\AdvancedManyToManyRelationAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\ClassificationStoreAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\RelationAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\IndexMappingService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\KeyLocaleService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\KeyLocaleServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\ClassificationStore\ServiceResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\AdvancedManyToManyRelation;
use Pimcore\Model\DataObject\ClassDefinition\Data\Checkbox;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyRelation;
use Pimcore\Model\DataObject\ClassDefinition\Data\MultiValue;
use Pimcore\Model\DataObject\Classificationstore as ClassificationstoreModel;
use Pimcore\Model\DataObject\Classificationstore\DefinitionCache;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\DataObject\Classificationstore\LocalizationResolver;
use Pimcore\Model\DataObject\Concrete;
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

    private const FOURTH_KEY_NAME = 'Material';

    private const KEY_ID = 42;

    private const STORE_ID = 7;

    private const METRIC = 'metric';

    private const IMPERIAL = 'imperial';

    private const OTHER_KEY_ID = 43;

    private const THIRD_KEY_ID = 44;

    private const FOURTH_KEY_ID = 45;

    private const GROUP_ID = 9;

    private const OTHER_GROUP_ID = 10;

    private const FIELD_NAME = 'csstore';

    private const GROUP_NAME = 'Specifications';

    private const DEFINITION_INDEXED = 'indexed';

    private const CACHE_KEY_INDEXED = 'gdi_cs_key_indexed_%d';

    private const KEY_MAPPING = ['type' => 'keyword'];

    private const PROPERTIES = 'properties';

    private const LANGUAGE_NODE = [self::PROPERTIES => [self::KEY_NAME => self::KEY_MAPPING]];

    private const DEFAULT_LANGUAGE = 'default';

    private const LANGUAGES = ['de', 'en'];

    private const ETIM_ASSIGNMENTS = 76625;

    private const ETIM_LOCALIZED_ASSIGNMENTS = 23222;

    private const ETIM_LANGUAGES = 17;

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
     * The language nodes of a group are declared per key, not per group: the non-localized node carries
     * every indexed key and a language node only the localized ones. The document writes exactly the
     * paths the mapping declares, so both are asserted over the same two relations and compared to each
     * other - a non-localized key contributes no "<language>" entry at all.
     */
    public function testLanguageNodesAndDocumentPathsAreDeclaredPerKey(): void
    {
        $fieldDefinition = $this->createFieldDefinition(false);
        $relations = [
            $this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME, true),
            $this->createRelation(self::OTHER_KEY_ID, self::GROUP_ID, self::OTHER_KEY_NAME),
        ];

        $mapping = $this->getMappingForRelations($fieldDefinition, self::LANGUAGES, $relations);
        $document = $this->getDocumentValues($fieldDefinition, self::LANGUAGES, $relations);

        $this->assertSame(
            [
                self::DEFAULT_LANGUAGE => [
                    self::PROPERTIES => [
                        self::KEY_NAME => self::KEY_MAPPING,
                        self::OTHER_KEY_NAME => self::KEY_MAPPING,
                    ],
                ],
                'de' => self::LANGUAGE_NODE,
                'en' => self::LANGUAGE_NODE,
            ],
            $mapping
        );

        $this->assertSame(
            [
                self::DEFAULT_LANGUAGE => [
                    self::KEY_NAME => self::KEY_ID,
                    self::OTHER_KEY_NAME => self::OTHER_KEY_ID,
                ],
                'de' => [self::KEY_NAME => self::KEY_ID],
                'en' => [self::KEY_NAME => self::KEY_ID],
            ],
            $document
        );

        // the mapping predicts the document: the same key set under every language node of the group
        $this->assertSame(
            array_map(static fn (array $node): array => array_keys($node[self::PROPERTIES]), $mapping),
            array_map('array_keys', $document)
        );
    }

    /**
     * The effective flag is the key's own, not the class field's: an explicitly localized key declares
     * the valid languages under a field that is not localized.
     */
    public function testAKeyFlaggedLocalizedDeclaresTheLanguagesOnANonLocalizedField(): void
    {
        $this->assertSame(
            [self::DEFAULT_LANGUAGE, 'de', 'en'],
            $this->getDeclaredLanguages(fieldLocalized: false, keyLocalized: true)
        );
    }

    public function testAKeyFlaggedNonLocalizedDeclaresNoLanguageOnALocalizedField(): void
    {
        $this->assertSame(
            [self::DEFAULT_LANGUAGE],
            $this->getDeclaredLanguages(fieldLocalized: true, keyLocalized: false)
        );
    }

    /**
     * The upgrade case: no key of an existing installation carries a flag, so every key of a localized
     * field still declares exactly what it declares today.
     */
    public function testAKeyWithoutAFlagInheritsALocalizedField(): void
    {
        $this->assertSame(
            [self::DEFAULT_LANGUAGE, 'de', 'en'],
            $this->getDeclaredLanguages(fieldLocalized: true, keyLocalized: null)
        );
    }

    public function testAKeyWithoutAFlagInheritsANonLocalizedField(): void
    {
        $this->assertSame(
            [self::DEFAULT_LANGUAGE],
            $this->getDeclaredLanguages(fieldLocalized: false, keyLocalized: null)
        );
    }

    public function testAKeyFlaggedLocalizedDeclaresTheLanguagesOnALocalizedField(): void
    {
        $this->assertSame(
            [self::DEFAULT_LANGUAGE, 'de', 'en'],
            $this->getDeclaredLanguages(fieldLocalized: true, keyLocalized: true)
        );
    }

    /**
     * The sixth cell of the flag matrix: a flag that agrees with the field it is rendered in changes
     * nothing, so neither explicit value is a special case of the other.
     */
    public function testAKeyFlaggedNonLocalizedDeclaresNoLanguageOnANonLocalizedField(): void
    {
        $this->assertSame(
            [self::DEFAULT_LANGUAGE],
            $this->getDeclaredLanguages(fieldLocalized: false, keyLocalized: false)
        );
    }

    /**
     * One fixture, both sides of the per key language set: the locale a caller has to filter a key
     * under is one of the language nodes the mapping declares for that key, over the whole flag
     * matrix. Both answers come from the same service, and this is what keeps them that way.
     */
    public function testTheFilterLocaleOfAKeyIsOneOfItsDeclaredLanguageNodes(): void
    {
        $keyLocaleService = $this->createKeyLocaleService();
        $language = self::LANGUAGES[0];

        foreach ([true, false] as $fieldLocalized) {
            foreach ([true, false, null] as $keyLocalized) {
                $case = 'field ' . var_export($fieldLocalized, true) . ', key ' . var_export($keyLocalized, true);
                $declaredLanguages = $this->getDeclaredLanguages($fieldLocalized, $keyLocalized);

                $locale = $keyLocaleService->getLocaleForKey(
                    self::KEY_ID,
                    $this->createFieldDefinition($fieldLocalized),
                    $language
                );

                $this->assertContains($locale, $declaredLanguages, $case);
                $this->assertSame(
                    ($keyLocalized ?? $fieldLocalized) ? $language : self::DEFAULT_LANGUAGE,
                    $locale,
                    $case
                );
            }
        }
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
     * The key mapping is built once per key and placed under each of its language nodes, so the
     * diagnostics of a key name do not repeat for every language the key is declared under.
     */
    public function testKeyDiagnosticsAreReportedOncePerKeyRegardlessOfLanguageCount(): void
    {
        $logger = $this->createCollectingLogger();

        $mapping = $this->getMappingForRelations(
            $this->createFieldDefinition(true),
            ['de', 'en', 'fr'],
            [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME . '.', true)],
            $logger
        );

        $this->assertSame([self::DEFAULT_LANGUAGE, 'de', 'en', 'fr'], array_keys($mapping));
        $this->assertCount(1, $logger->records);
        $this->assertSame('info', $logger->records[0]['level']);
    }

    /**
     * A key marked as not indexed on its own definition contributes no mapping property, under none of
     * the declared language nodes, while every other key of the group is mapped as before.
     */
    public function testKeyExcludedOnItsDefinitionContributesNoMappingProperty(): void
    {
        $relations = [
            $this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME),
            $this->excludeOnDefinition(
                $this->createRelation(self::OTHER_KEY_ID, self::GROUP_ID, self::OTHER_KEY_NAME)
            ),
        ];

        $mapping = $this->getMappingForRelations($this->createFieldDefinition(true), self::LANGUAGES, $relations);

        $this->assertSame([self::DEFAULT_LANGUAGE, 'de', 'en'], array_keys($mapping));
        foreach ($mapping as $language => $languageNode) {
            $this->assertSame(self::LANGUAGE_NODE, $languageNode, $language);
        }
    }

    /**
     * The exclusion reaches the document as well, so the value of an excluded key is in neither the
     * indexed document nor _source, while the other keys of the same group keep theirs.
     */
    public function testKeyExcludedOnItsDefinitionContributesNoDocumentValue(): void
    {
        $relations = [
            $this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME),
            $this->excludeOnDefinition(
                $this->createRelation(self::OTHER_KEY_ID, self::GROUP_ID, self::OTHER_KEY_NAME)
            ),
        ];

        $this->assertSame(
            [
                self::DEFAULT_LANGUAGE => [self::KEY_NAME => self::KEY_ID],
                'de' => [self::KEY_NAME => self::KEY_ID],
            ],
            $this->getDocumentValues($this->createFieldDefinition(true), ['de'], $relations)
        );
    }

    /**
     * The flag belongs to the key, not to one of its assignments: the same key id is excluded from the
     * mapping and the document of every group it is assigned to.
     */
    public function testTheSameKeyIsExcludedInEveryGroupItIsAssignedTo(): void
    {
        $fieldDefinition = $this->createFieldDefinition(false);

        $first = $this->excludeOnDefinition($this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME));
        $second = $this->excludeOnDefinition(
            $this->createRelation(self::KEY_ID, self::OTHER_GROUP_ID, self::KEY_NAME)
        );

        $this->assertSame([], $this->getMappingForRelations($fieldDefinition, [], [$first]));
        $this->assertSame([], $this->getMappingForRelations($fieldDefinition, [], [$second]));

        $this->assertSame([], $this->getDocumentValues($fieldDefinition, [], [$first], self::GROUP_ID));
        $this->assertSame([], $this->getDocumentValues($fieldDefinition, [], [$second], self::OTHER_GROUP_ID));
    }

    /**
     * Nothing is excluded unless the flag says so, which is what the documented default promises and
     * wider than the flag itself needs: a definition without the flag, an empty one, an unparsable one
     * and one that reads as a JSON scalar rather than an object are all mapped and written as before.
     */
    public function testKeyWithoutIndexFlagIsIndexedAsBefore(): void
    {
        $fieldDefinition = $this->createFieldDefinition(false);

        $withoutFlag = $this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME);

        $emptyDefinition = $this->createRelation(self::OTHER_KEY_ID, self::GROUP_ID, self::OTHER_KEY_NAME);
        $emptyDefinition->setDefinition('');

        $unparsableDefinition = $this->createRelation(self::THIRD_KEY_ID, self::GROUP_ID, self::THIRD_KEY_NAME);
        $unparsableDefinition->setDefinition('{"fieldtype": "input"');

        $scalarDefinition = $this->createRelation(self::FOURTH_KEY_ID, self::GROUP_ID, self::FOURTH_KEY_NAME);
        $scalarDefinition->setDefinition('null');

        $relations = [$withoutFlag, $emptyDefinition, $unparsableDefinition, $scalarDefinition];

        $this->assertSame(
            [
                self::DEFAULT_LANGUAGE => [
                    self::PROPERTIES => [
                        self::KEY_NAME => self::KEY_MAPPING,
                        self::OTHER_KEY_NAME => self::KEY_MAPPING,
                        self::THIRD_KEY_NAME => self::KEY_MAPPING,
                        self::FOURTH_KEY_NAME => self::KEY_MAPPING,
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
                    self::FOURTH_KEY_NAME => self::FOURTH_KEY_ID,
                ],
            ],
            $this->getDocumentValues($fieldDefinition, [], $relations)
        );
    }

    /**
     * Regression: the flag used to be per assignment relation metadata. An assignment that still
     * carries "indexed": false there is indexed, because only the key's own definition is read.
     */
    public function testRelationMetadataIsNoLongerReadForTheIndexFlag(): void
    {
        $fieldDefinition = $this->createFieldDefinition(false);

        $relation = $this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME);
        $relation->setMetadata([self::DEFINITION_INDEXED => false]);

        $this->assertSame(
            [self::DEFAULT_LANGUAGE => self::LANGUAGE_NODE],
            $this->getMappingForRelations($fieldDefinition, [], [$relation])
        );
        $this->assertSame(
            [self::DEFAULT_LANGUAGE => [self::KEY_NAME => self::KEY_ID]],
            $this->getDocumentValues($fieldDefinition, [], [$relation])
        );
    }

    /**
     * A relation that was never hydrated cannot answer which key it assigns: KeyGroupRelation declares
     * "keyId" and "definition" as typed properties without a default, so reading either is an Error and
     * not a value to fall back on. The key is skipped instead, on all three paths that ask whether it is
     * indexed, and the keys around it are mapped and written as before.
     */
    public function testARelationThatWasNeverHydratedIsSkippedRatherThanRead(): void
    {
        $fieldDefinition = $this->createFieldDefinition(false);

        $withoutDefinition = new KeyGroupRelation();
        $withoutDefinition->setKeyId(self::OTHER_KEY_ID);
        $withoutDefinition->setGroupId(self::GROUP_ID);
        $withoutDefinition->setName(self::OTHER_KEY_NAME);

        $withoutKeyId = new KeyGroupRelation();
        $withoutKeyId->setGroupId(self::GROUP_ID);
        $withoutKeyId->setName(self::THIRD_KEY_NAME);
        $withoutKeyId->setDefinition('{}');

        $relations = [
            $this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME),
            $withoutDefinition,
            $withoutKeyId,
        ];

        $this->assertSame(
            [self::DEFAULT_LANGUAGE => self::LANGUAGE_NODE],
            $this->getMappingForRelations($fieldDefinition, [], $relations)
        );
        $this->assertSame(
            [self::DEFAULT_LANGUAGE => [self::KEY_NAME => self::KEY_ID]],
            $this->getDocumentValues($fieldDefinition, [], $relations)
        );

        $keyMapping = (new ReflectionMethod(ClassificationStoreAdapter::class, 'getKeyMappingForInheritance'))
            ->invoke($this->createConfiguredAdapter([], $fieldDefinition), $relations);

        $this->assertSame([self::KEY_ID], array_keys($keyMapping));
    }

    /**
     * The flag is a constant of the key, so it is parsed once and memoized per key id - which is the
     * whole of its scope, since the relation listing hands every assignment the key's own definition.
     * normalize() gets a new adapter for every indexed object, so without the memo each of them would
     * parse the definition of every key of every active group again. A relation that cannot answer is
     * not memoized: that says nothing about the key, and a hydrated assignment of it still resolves.
     */
    public function testTheIndexFlagIsParsedOncePerKeyIdAndNotMemoizedForAnUnhydratedRelation(): void
    {
        $entries = new ArrayObject();
        $resolver = $this->createRuntimeCacheResolver($entries);
        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'isIndexedKey');

        $excluded = $this->excludeOnDefinition($this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME));
        $sameKeyOtherGroup = $this->createRelation(self::KEY_ID, self::OTHER_GROUP_ID, self::KEY_NAME);

        $unhydrated = new KeyGroupRelation();
        $unhydrated->setKeyId(self::OTHER_KEY_ID);
        $unhydrated->setGroupId(self::GROUP_ID);
        $unhydrated->setName(self::OTHER_KEY_NAME);

        $hydrated = $this->createRelation(self::OTHER_KEY_ID, self::GROUP_ID, self::OTHER_KEY_NAME);

        // a fresh adapter per call, which is what the document path gets for every indexed object
        $this->assertFalse($method->invoke($this->createConfiguredAdapter([], null, null, $resolver), $excluded));
        $this->assertFalse(
            $method->invoke($this->createConfiguredAdapter([], null, null, $resolver), $sameKeyOtherGroup)
        );
        $this->assertFalse($method->invoke($this->createConfiguredAdapter([], null, null, $resolver), $unhydrated));
        $this->assertTrue($method->invoke($this->createConfiguredAdapter([], null, null, $resolver), $hydrated));

        $this->assertSame(
            [
                sprintf(self::CACHE_KEY_INDEXED, self::KEY_ID) => false,
                sprintf(self::CACHE_KEY_INDEXED, self::OTHER_KEY_ID) => true,
            ],
            $entries->getArrayCopy()
        );
    }

    /**
     * The memo is reached only after both properties are known to be there, so an unhydrated
     * assignment of an already memoized key is still skipped. The order matters: the hydrated
     * assignment caches "true" for the key id, and the unhydrated one shares that key id, so a memo
     * consulted on "keyId" alone would answer "true" for a relation whose "definition" the callers
     * then read - the reverse of the order
     * {@see self::testTheIndexFlagIsParsedOncePerKeyIdAndNotMemoizedForAnUnhydratedRelation} covers.
     */
    public function testAnUnhydratedAssignmentOfAnAlreadyMemoizedKeyIsStillSkipped(): void
    {
        $entries = new ArrayObject();
        $resolver = $this->createRuntimeCacheResolver($entries);
        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'isIndexedKey');

        $hydrated = $this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME);

        $unhydrated = new KeyGroupRelation();
        $unhydrated->setKeyId(self::KEY_ID);
        $unhydrated->setGroupId(self::OTHER_GROUP_ID);
        $unhydrated->setName(self::KEY_NAME);

        $this->assertTrue($method->invoke($this->createConfiguredAdapter([], null, null, $resolver), $hydrated));
        $this->assertSame(
            [sprintf(self::CACHE_KEY_INDEXED, self::KEY_ID) => true],
            $entries->getArrayCopy(),
            'the hydrated assignment memoizes the key as indexed'
        );

        $this->assertFalse(
            $method->invoke($this->createConfiguredAdapter([], null, null, $resolver), $unhydrated),
            'the cached "true" must not be returned for a relation that cannot answer'
        );
    }

    /**
     * The same guarantee through the callers, in the shape that actually reaches them: the memo spans
     * key ids, so it spans groups, and getIndexMapping() walks every group of the store with one
     * adapter. The first group hydrates the key and memoizes it as indexed; the second assigns the
     * same key unhydrated, and that group must declare nothing rather than resolve a definition off a
     * relation that has none. Same adapter for both calls, and a group of its own for each, so the
     * per-call name bookkeeping cannot be what drops the second one.
     */
    public function testAMemoizedKeyDoesNotLetAnUnhydratedAssignmentOfAnotherGroupReachDefinitionResolution(): void
    {
        $adapter = $this->createConfiguredAdapter([], $this->createFieldDefinition(false));
        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'getMappingForGroupConfig');
        $languages = $this->getDeclarableLanguages($adapter);

        $unhydrated = new KeyGroupRelation();
        $unhydrated->setKeyId(self::KEY_ID);
        $unhydrated->setGroupId(self::OTHER_GROUP_ID);
        $unhydrated->setName(self::KEY_NAME);

        $this->assertSame(
            [self::DEFAULT_LANGUAGE => self::LANGUAGE_NODE],
            $method->invoke(
                $adapter,
                [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME)],
                $languages
            ),
            'the first group hydrates the key and memoizes it as indexed'
        );

        $this->assertSame(
            [],
            $method->invoke($adapter, [$unhydrated], $languages),
            'the cached "true" must not declare a key the second group cannot resolve'
        );
    }

    /**
     * The excluded key is dropped before its name is normalized, so it does not reserve the segment
     * "Size" that the key named "Size." normalizes to - on the mapping and on the document path alike.
     */
    public function testExcludedKeyDoesNotReserveItsNormalizedNameSegment(): void
    {
        $fieldDefinition = $this->createFieldDefinition(false);

        $excluded = $this->excludeOnDefinition($this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME));
        $indexed = $this->createRelation(self::OTHER_KEY_ID, self::GROUP_ID, self::KEY_NAME . '.');

        $this->assertSame(
            [self::DEFAULT_LANGUAGE => self::LANGUAGE_NODE],
            $this->getMappingForRelations($fieldDefinition, [], [$excluded, $indexed])
        );

        // the stubbed value is the key id, so it names the key the mapped "Size" was written from
        $this->assertSame(
            [self::DEFAULT_LANGUAGE => [self::KEY_NAME => self::OTHER_KEY_ID]],
            $this->getDocumentValues($fieldDefinition, [], [$excluded, $indexed])
        );
    }

    /**
     * The third path the invariant covers: an excluded key emits no inherited field path either, so the
     * mapping, the document and the inheritance path keep dropping the same keys.
     */
    public function testExcludedKeyProducesNoInheritedFieldPath(): void
    {
        $excluded = $this->excludeOnDefinition(
            $this->createRelation(self::OTHER_KEY_ID, self::GROUP_ID, self::OTHER_KEY_NAME)
        );

        $keyMapping = (new ReflectionMethod(ClassificationStoreAdapter::class, 'getKeyMappingForInheritance'))
            ->invoke(
                $this->createConfiguredAdapter([]),
                [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME), $excluded]
            );

        $this->assertSame([self::KEY_ID], array_keys($keyMapping));
        $this->assertSame(self::KEY_NAME, $keyMapping[self::KEY_ID]['name']);
    }

    /**
     * The inherited field paths follow the same per key language set as the mapping and the document: a
     * non-localized key emits "<field>.<group>.<key>" alone, a localized one one path per valid
     * language, instead of every language for every key.
     */
    public function testInheritedFieldPathsCarryTheLanguageOnlyForALocalizedKey(): void
    {
        $this->registerKey(self::KEY_ID, true);
        $this->registerKey(self::OTHER_KEY_ID, null);

        $adapter = $this->createConfiguredAdapter(self::LANGUAGES, $this->createFieldDefinition(false));
        $languages = $this->getDeclarableLanguages($adapter);
        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'getInheritancePathsForKey');

        $this->assertSame(
            [
                self::DEFAULT_LANGUAGE => 'csstore.Specifications.Size',
                'de' => 'csstore.Specifications.Size.de',
                'en' => 'csstore.Specifications.Size.en',
            ],
            $method->invoke($adapter, self::FIELD_NAME, self::GROUP_NAME, self::KEY_NAME, self::KEY_ID, $languages)
        );

        $this->assertSame(
            [self::DEFAULT_LANGUAGE => 'csstore.Specifications.Weight'],
            $method->invoke(
                $adapter,
                self::FIELD_NAME,
                self::GROUP_NAME,
                self::OTHER_KEY_NAME,
                self::OTHER_KEY_ID,
                $languages
            )
        );
    }

    /**
     * The fourth path the invariant covers: the inheritance side enumerates the same entries as the
     * mapping and the document, so every variant sibling is an entry an inherited path is built for
     * and the sibling name is what {@see ClassificationStoreAdapter::getInheritancePathsForKey()} is
     * then given. A sibling name another key already holds is dropped here as it is dropped there.
     */
    public function testTheInheritedEntriesOfAKeyAreItsBareNameAndOneSiblingPerDeclaredVariant(): void
    {
        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'getKeyMappingForInheritance');
        $entries = new ReflectionMethod(ClassificationStoreAdapter::class, 'getEntryVariants');

        $adapter = $this->createConfiguredAdapter(
            [],
            keyDefinition: $this->createMultiValue([self::METRIC, self::IMPERIAL], true)
        );
        $keyMapping = $method->invoke(
            $adapter,
            [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME)]
        );

        $this->assertTrue($keyMapping[self::KEY_ID]['multiValue']);
        $this->assertSame(
            [
                self::KEY_NAME => null,
                self::KEY_NAME . '_' . self::METRIC => self::METRIC,
                self::KEY_NAME . '_' . self::IMPERIAL => self::IMPERIAL,
            ],
            $entries->invoke($adapter, $keyMapping[self::KEY_ID])
        );

        $collidingAdapter = $this->createConfiguredAdapter(
            [],
            keyDefinition: fn (KeyGroupRelation $relation): Data => $relation->getKeyId() === self::KEY_ID
                ? $this->createMultiValue([self::METRIC])
                : new Input()
        );
        $collided = $method->invoke(
            $collidingAdapter,
            [
                $this->createRelation(self::OTHER_KEY_ID, self::GROUP_ID, self::KEY_NAME . '_' . self::METRIC),
                $this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME),
            ]
        );

        // the earlier key holds "Size_metric", so the variant of "Size" contributes no entry here either
        $this->assertSame([self::OTHER_KEY_ID, self::KEY_ID], array_keys($collided));
        $this->assertSame(
            [self::KEY_NAME => null],
            $entries->invoke($collidingAdapter, $collided[self::KEY_ID])
        );
    }

    /**
     * The provenance of a sibling is resolved against the sibling's own slot: a variant the child does
     * not hold and a parent does is attributed to the parent, instead of the whole key taking the
     * verdict of position 0 of its default variant. A `multiValue` entry is resolved against the whole
     * list the bare entry carries, so a parent holding only a higher position is found as well.
     */
    public function testASiblingTakesItsInheritanceOriginFromItsOwnVariantSlot(): void
    {
        $method = new ReflectionMethod(ClassificationStoreAdapter::class, 'getKeyValueFromElement');
        $definition = $this->createMultiValue([self::METRIC, self::IMPERIAL]);

        $parent = $this->createObjectWithStore(10, [self::METRIC => [0 => 'parent metric']]);
        $child = $this->createObjectWithStore(
            1,
            [self::IMPERIAL => [0 => 'child imperial']],
            'own default',
            $parent
        );

        $origin = fn (?string $variant, bool $multiValue = false): ?int => $method->invoke(
            $this->createConfiguredAdapter([]),
            $definition,
            $child,
            self::FIELD_NAME,
            self::GROUP_ID,
            self::KEY_ID,
            self::DEFAULT_LANGUAGE,
            $variant,
            $multiValue
        );

        $this->assertSame(1, $origin(null));
        $this->assertSame(10, $origin(self::METRIC));
        $this->assertSame(1, $origin(self::IMPERIAL));

        // a list the child does not hold at all and the parent holds above position 0
        $listParent = $this->createObjectWithStore(10, [self::DEFAULT_LANGUAGE => [1 => 'second']]);
        $listChild = $this->createObjectWithStore(1, [], '', $listParent);

        $this->assertSame(
            10,
            $method->invoke(
                $this->createConfiguredAdapter([]),
                $this->createMultiValue(multiValue: true),
                $listChild,
                self::FIELD_NAME,
                self::GROUP_ID,
                self::KEY_ID,
                self::DEFAULT_LANGUAGE,
                null,
                true
            )
        );
    }

    /**
     * A store whose engine cannot address variants refuses the read. The sibling then holds nothing
     * anywhere on the chain, which is the verdict an empty chain already gets - the topmost ancestor -
     * and the walk adds no log line of its own: the write path reports that slot of this object in the
     * same index run, so repeating it per ancestor would multiply one configuration mistake by the
     * depth of the tree.
     */
    public function testAVariantSlotTheEngineRefusesIsNotReportedAgainByTheInheritanceWalk(): void
    {
        $logger = $this->createCollectingLogger();
        $refusing = fn (int $id, ?Concrete $parent = null): Concrete => $this->makeEmpty(Concrete::class, [
            'getId' => $id,
            'getNextParentForInheritance' => $parent,
            'get' => $this->makeEmpty(ClassificationstoreModel::class, [
                'getLocalizedKeyValue' => 'own default',
                'getKeyValues' => static function (): never {
                    throw new Exception('the engine behind this store does not support variants');
                },
            ]),
        ]);

        $this->assertSame(
            10,
            (new ReflectionMethod(ClassificationStoreAdapter::class, 'getKeyValueFromElement'))->invoke(
                $this->createConfiguredAdapter([], logger: $logger),
                $this->createMultiValue([self::METRIC]),
                $refusing(1, $refusing(10)),
                self::FIELD_NAME,
                self::GROUP_ID,
                self::KEY_ID,
                self::DEFAULT_LANGUAGE,
                self::METRIC,
                false
            )
        );
        $this->assertSame([], $logger->records);
    }

    /**
     * The declared field count of the classification store field at ETIM proportions: 76,625 key
     * assignments, 17 valid languages plus the non-localized node, 23,222 of the assignments localized,
     * and no group restriction. Each group contributes its own leaves and each key of a group its own,
     * so the quantity measured here is the leaf count of a single key at the language count of the
     * shape, and the two totals are projected from it; the additivity the projection rests on is
     * asserted on a mixed pair of the same keys. A unit suite is not where throughput is measured, so
     * the volume stays out of it. The stub maps one leaf per key, which is the unit the 1,379,250
     * baseline counts.
     *
     * Before: every key under every language node - what a localized field declared for all of its keys
     * and what a key without a flag still inherits - 76,625 x 18 = 1,379,250.
     * After: the non-localized node for every key plus the languages of the localized ones -
     * 76,625 + 23,222 x 17 = 471,399.
     */
    public function testTheDeclaredFieldCountOfAnEtimShapedStore(): void
    {
        $languages = array_map(static fn (int $index): string => 'l' . $index, range(1, self::ETIM_LANGUAGES));

        $inheritingOnALocalizedField = $this->countDeclaredFieldsOfKeys($languages, true, [self::KEY_ID => null]);
        $localizedKey = $this->countDeclaredFieldsOfKeys($languages, false, [self::KEY_ID => true]);
        $nonLocalizedKey = $this->countDeclaredFieldsOfKeys($languages, false, [self::KEY_ID => false]);

        // the projection is additive over the keys of a group, which is what a mixed group has to show
        $this->assertSame(
            $localizedKey + $nonLocalizedKey,
            $this->countDeclaredFieldsOfKeys(
                $languages,
                false,
                [self::KEY_ID => true, self::OTHER_KEY_ID => false]
            )
        );

        $before = self::ETIM_ASSIGNMENTS * $inheritingOnALocalizedField;
        $after = self::ETIM_LOCALIZED_ASSIGNMENTS * $localizedKey
            + (self::ETIM_ASSIGNMENTS - self::ETIM_LOCALIZED_ASSIGNMENTS) * $nonLocalizedKey;

        $this->assertSame(1379250, $before);
        $this->assertSame(471399, $after);
    }

    /**
     * A key whose type resolves to no adapter is reported once, at error level, and the mapping
     * carries on: nothing is thrown, because IndexMappingService drops the whole field when it is.
     */
    public function testAKeyTypeWithNoAdapterIsReportedAtErrorLevelAndTheRestOfTheGroupIsStillMapped(): void
    {
        $logger = $this->createCollectingLogger();

        $mapping = $this->getMappingForRelations(
            $this->createFieldDefinition(false),
            self::LANGUAGES,
            $this->createKeysWithNoAdapterForTheFirst(),
            $logger,
            $this->createDefinitionPerKey(),
            $this->createServiceWithoutAdapterForCheckbox()
        );

        $this->assertCount(1, $logger->records);
        $this->assertSame('error', $logger->records[0]['level']);
        foreach ([self::STORE_ID, self::GROUP_ID, self::KEY_ID, self::KEY_NAME, 'checkbox'] as $part) {
            $this->assertStringContainsString((string) $part, $logger->records[0]['message']);
        }

        $this->assertSame(
            [self::DEFAULT_LANGUAGE => [self::PROPERTIES => [self::OTHER_KEY_NAME => self::KEY_MAPPING]]],
            $mapping
        );
    }

    public function testAKeyWithNoAdapterDoesNotDropTheClassificationStoreFieldFromTheMapping(): void
    {
        $fieldDefinition = $this->createFieldDefinition(false);
        $fieldDefinition->setName(self::FIELD_NAME);
        $adapter = $this->createConfiguredAdapter(
            self::LANGUAGES,
            $fieldDefinition,
            keyDefinition: $this->createDefinitionPerKey(),
            fieldDefinitionService: $this->createServiceWithoutAdapterForCheckbox()
        );
        $relations = $this->createKeysWithNoAdapterForTheFirst();
        $languages = $this->getDeclarableLanguages($adapter);

        $storeAdapter = $this->makeEmpty(AdapterInterface::class, [
            'getIndexAttributeName' => self::FIELD_NAME,
            'getIndexMapping' => fn (): array => (new ReflectionMethod(
                ClassificationStoreAdapter::class,
                'getMappingForGroupConfig'
            ))->invoke($adapter, $relations, $languages),
        ]);

        $mapping = (new IndexMappingService(
            $this->makeEmpty(FieldDefinitionServiceInterface::class, ['getFieldDefinitionAdapter' => $storeAdapter]),
            $this->makeEmpty(SearchIndexConfigServiceInterface::class, [
                'getClientType' => ClientType::OPEN_SEARCH->value,
            ])
        ))->getMappingForFieldDefinitions([$fieldDefinition]);

        $this->assertSame(
            [self::OTHER_KEY_NAME => self::KEY_MAPPING],
            $mapping[self::PROPERTIES][self::FIELD_NAME][self::DEFAULT_LANGUAGE][self::PROPERTIES]
        );
    }

    /**
     * Reported from the mapping path only: the document path runs per element and per locale.
     */
    public function testTheMissingAdapterIsReportedOncePerKeyAndNotFromTheDocumentPath(): void
    {
        $logger = $this->createCollectingLogger();
        $fieldDefinition = $this->createFieldDefinition(true);
        $relations = [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME, true)];
        $definition = new Checkbox();
        $service = $this->makeEmpty(FieldDefinitionServiceInterface::class, [
            'getFieldDefinitionAdapter' => null,
            'normalizeValue' => null,
        ]);

        $mapping = $this->getMappingForRelations(
            $fieldDefinition,
            ['de', 'en', 'fr'],
            $relations,
            $logger,
            $definition,
            $service
        );
        $this->assertSame([], $mapping);
        $this->assertCount(1, $logger->records);

        $this->getDocumentValues(
            $fieldDefinition,
            ['de', 'en', 'fr'],
            $relations,
            keyDefinition: $definition,
            logger: $logger,
            fieldDefinitionService: $service
        );
        $this->assertCount(1, $logger->records);
    }

    /**
     * A key of type X declares what a class field of type X declares, advanced relation details included.
     */
    public function testRelationKeysDeclareExactlyTheMappingOfTheSameTypeOnAClassField(): void
    {
        $advanced = new AdvancedManyToManyRelation();
        $advanced->setColumns([['type' => 'number', 'key' => 'key1', 'position' => 1]]);

        foreach ([new ManyToManyRelation(), $advanced] as $definition) {
            $mapping = $this->getMappingForRelations(
                $this->createFieldDefinition(false),
                self::LANGUAGES,
                [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME)],
                null,
                $definition,
                $this->createServiceWithRelationAdapters()
            );

            $classFieldMapping = $this->createServiceWithRelationAdapters()
                ->getFieldDefinitionAdapter($definition)
                ->getIndexMapping();

            $this->assertSame(
                $classFieldMapping,
                $mapping[self::DEFAULT_LANGUAGE][self::PROPERTIES][self::KEY_NAME]
            );
        }
    }

    public function testAMultiValueKeyDeclaresNoLeafOfItsOwnAndWritesAListInPositionOrder(): void
    {
        $fieldDefinition = $this->createFieldDefinition(false);
        $relations = [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME)];
        $multiValue = $this->createMultiValue(multiValue: true);

        $this->assertSame(
            $this->getMappingForRelations($fieldDefinition, self::LANGUAGES, $relations),
            $this->getMappingForRelations($fieldDefinition, self::LANGUAGES, $relations, null, $multiValue)
        );

        $this->assertSame(
            [self::DEFAULT_LANGUAGE => [self::KEY_NAME => ['first', 'second', 'third']]],
            $this->getQualifiedDocumentValues(
                $multiValue,
                $relations,
                [2 => 'third', 0 => 'first', 1 => 'second']
            )
        );
        $this->assertSame(
            [self::DEFAULT_LANGUAGE => [self::KEY_NAME => ['only']]],
            $this->getQualifiedDocumentValues($multiValue, $relations, [0 => 'only'])
        );
    }

    public function testEachDeclaredVariantIsASiblingPropertyUnderTheNodesOfTheKey(): void
    {
        $fieldDefinition = $this->createFieldDefinition(false);
        $variants = $this->createMultiValue([self::METRIC, self::IMPERIAL]);
        $siblings = [
            self::KEY_NAME => self::KEY_MAPPING,
            self::KEY_NAME . '_' . self::METRIC => self::KEY_MAPPING,
            self::KEY_NAME . '_' . self::IMPERIAL => self::KEY_MAPPING,
        ];

        $this->assertSame(
            [self::DEFAULT_LANGUAGE => [self::PROPERTIES => $siblings]],
            $this->getMappingForRelations(
                $fieldDefinition,
                self::LANGUAGES,
                [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME, false)],
                null,
                $variants
            )
        );

        $mapping = $this->getMappingForRelations(
            $fieldDefinition,
            self::LANGUAGES,
            [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME, true)],
            null,
            $variants
        );
        $this->assertSame([self::DEFAULT_LANGUAGE, 'de', 'en'], array_keys($mapping));
        foreach ($mapping as $node) {
            $this->assertSame([self::PROPERTIES => $siblings], $node);
        }
    }

    public function testASiblingNameAnotherKeyAlreadyHoldsIsSkippedWithAWarningAndTheEarlierOneWins(): void
    {
        $logger = $this->createCollectingLogger();

        $mapping = $this->getMappingForRelations(
            $this->createFieldDefinition(false),
            self::LANGUAGES,
            [
                $this->createRelation(self::OTHER_KEY_ID, self::GROUP_ID, self::KEY_NAME . '_' . self::METRIC),
                $this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME),
            ],
            $logger,
            fn (KeyGroupRelation $relation): Data => $relation->getKeyId() === self::KEY_ID
                ? $this->createMultiValue([self::METRIC])
                : new Input()
        );

        $this->assertSame(
            [self::KEY_NAME . '_' . self::METRIC, self::KEY_NAME],
            array_keys($mapping[self::DEFAULT_LANGUAGE][self::PROPERTIES])
        );
        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
    }

    public function testTheDefaultVariantStaysInTheBareEntryAndEachVariantIsWrittenToItsSibling(): void
    {
        $this->assertSame(
            [
                self::DEFAULT_LANGUAGE => [
                    self::KEY_NAME => 'default value',
                    self::KEY_NAME . '_' . self::METRIC => 'metric value',
                    self::KEY_NAME . '_' . self::IMPERIAL => 'imperial value',
                ],
            ],
            $this->getDocumentValues(
                $this->createFieldDefinition(false),
                self::LANGUAGES,
                [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME)],
                keyDefinition: $this->createMultiValue([self::METRIC, self::IMPERIAL]),
                store: $this->makeEmpty(ClassificationstoreModel::class, [
                    'getLocalizedKeyValue' => 'default value',
                    'getKeyValues' => static fn (
                        int $group,
                        int $keyId,
                        ?string $language,
                        ?string $variant
                    ): array => [0 => $variant . ' value'],
                ])
            )
        );
    }

    /**
     * A key that declares both carries a list in every slot it holds: the sibling of a declared
     * variant is an array of that variant's positions in position order, independent of the bare
     * entry's list, where a key without `multiValue` writes the one value its sibling permits. A
     * declared variant the store holds nothing for writes no sibling entry at all.
     */
    public function testASiblingOfAMultiValueKeyCarriesItsOwnListInPositionOrder(): void
    {
        $slots = [
            self::DEFAULT_LANGUAGE => [1 => 'default second', 0 => 'default first'],
            self::METRIC => [2 => 'metric third', 0 => 'metric first', 1 => 'metric second'],
            self::IMPERIAL => [],
        ];

        $this->assertSame(
            [
                self::DEFAULT_LANGUAGE => [
                    self::KEY_NAME => ['default first', 'default second'],
                    self::KEY_NAME . '_' . self::METRIC => ['metric first', 'metric second', 'metric third'],
                ],
            ],
            $this->getDocumentValues(
                $this->createFieldDefinition(false),
                self::LANGUAGES,
                [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME)],
                keyDefinition: $this->createMultiValue([self::METRIC, self::IMPERIAL], true),
                store: $this->makeEmpty(ClassificationstoreModel::class, [
                    'getKeyValues' => static fn (
                        int $group,
                        int $keyId,
                        ?string $language,
                        ?string $variant
                    ): array => $slots[$variant ?? self::DEFAULT_LANGUAGE] ?? [],
                    'getLocalizedKeyValue' => static function (): never {
                        throw new Exception('a multiValue key is read through getKeyValues');
                    },
                ])
            )
        );
    }

    /**
     * Pins today's shape: without variants or multiValue the entry is the one value, read the way it
     * is read today, and the qualified reader is never asked.
     */
    public function testAKeyWithoutQualifiersWritesASingleNonArrayEntry(): void
    {
        $document = $this->getDocumentValues(
            $this->createFieldDefinition(false),
            self::LANGUAGES,
            [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME)],
            store: $this->makeEmpty(ClassificationstoreModel::class, [
                'getLocalizedKeyValue' => 'plain',
                'getKeyValues' => static function (): never {
                    throw new Exception('a key without qualifiers is read through getLocalizedKeyValue');
                },
            ])
        );

        $this->assertSame([self::DEFAULT_LANGUAGE => [self::KEY_NAME => 'plain']], $document);
    }

    /**
     * Declared field count at the ETIM shape, one number per thing that changes it: multiValue adds
     * none, a variant on every key doubles it, and a localized relation key declares three leaves on
     * each of its 18 nodes where a scalar key declares one.
     */
    public function testTheDeclaredFieldCountOfAnEtimShapedStoreWithQualifiersAndRelationKeys(): void
    {
        $languages = array_map(static fn (int $index): string => 'l' . $index, range(1, self::ETIM_LANGUAGES));
        $localizedKeys = self::ETIM_LOCALIZED_ASSIGNMENTS;
        $otherKeys = self::ETIM_ASSIGNMENTS - $localizedKeys;
        $nodes = self::ETIM_LANGUAGES + 1;

        $count = fn (bool $localized, mixed $definition = null, ?FieldDefinitionServiceInterface $service = null): int
            => $this->countDeclaredFieldsOfKeys($languages, false, [self::KEY_ID => $localized], $definition, $service);

        $baseline = $localizedKeys * $count(true) + $otherKeys * $count(false);
        $multiValue = $this->createMultiValue(multiValue: true);
        $withMultiValue = $localizedKeys * $count(true, $multiValue) + $otherKeys * $count(false, $multiValue);
        $variants = $this->createMultiValue([self::METRIC]);
        $withVariants = $localizedKeys * $count(true, $variants) + $otherKeys * $count(false, $variants);

        $relationLeaves = $count(true, new ManyToManyRelation(), $this->createServiceWithRelationAdapters());
        $withOneRelationKey = $baseline - $nodes + $relationLeaves;

        $this->assertSame(471399, $baseline);
        $this->assertSame($baseline, $withMultiValue);
        $this->assertSame(942798, $withVariants);
        $this->assertSame($nodes * 3, $relationLeaves);
        $this->assertSame(471399 - 18 + 54, $withOneRelationKey);
    }

    /**
     * @param array<int, mixed> $values
     * @param KeyGroupRelation[] $relations
     */
    private function getQualifiedDocumentValues(MultiValue $definition, array $relations, array $values): array
    {
        return $this->getDocumentValues(
            $this->createFieldDefinition(false),
            self::LANGUAGES,
            $relations,
            keyDefinition: $definition,
            store: $this->makeEmpty(ClassificationstoreModel::class, [
                'getKeyValues' => $values,
                'getLocalizedKeyValue' => static function (): never {
                    throw new Exception('a multiValue key is read through getKeyValues');
                },
            ])
        );
    }

    /**
     * An element whose own store holds the given qualified slots, keyed by variant name - the default
     * variant under {@see self::DEFAULT_LANGUAGE}, which is the name a slot addressed with no variant
     * resolves to - and the given value at position 0 of the default variant for the resolving reader.
     *
     * @param array<string, array<int, mixed>> $slots
     */
    private function createObjectWithStore(
        int $id,
        array $slots,
        string $defaultValue = '',
        ?Concrete $parent = null
    ): Concrete {
        $store = $this->makeEmpty(ClassificationstoreModel::class, [
            'getLocalizedKeyValue' => $defaultValue,
            'getKeyValues' => static fn (
                int $group,
                int $keyId,
                ?string $language,
                ?string $variant
            ): array => $slots[$variant ?? self::DEFAULT_LANGUAGE] ?? [],
        ]);

        return $this->makeEmpty(Concrete::class, [
            'getId' => $id,
            'getNextParentForInheritance' => $parent,
            'get' => $store,
        ]);
    }

    /**
     * @param string[] $variants
     */
    private function createMultiValue(array $variants = [], bool $multiValue = false): MultiValue
    {
        $definition = new MultiValue();
        $definition->setVariants($variants);
        $definition->setMultiValue($multiValue);
        $definition->setDelegate(new Input());

        return $definition;
    }

    /**
     * @return KeyGroupRelation[]
     */
    private function createKeysWithNoAdapterForTheFirst(): array
    {
        return [
            $this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME),
            $this->createRelation(self::OTHER_KEY_ID, self::GROUP_ID, self::OTHER_KEY_NAME),
        ];
    }

    private function createDefinitionPerKey(): callable
    {
        return static fn (KeyGroupRelation $relation): Data => $relation->getKeyId() === self::KEY_ID
            ? new Checkbox()
            : new Input();
    }

    private function createServiceWithoutAdapterForCheckbox(): FieldDefinitionServiceInterface
    {
        return $this->makeEmpty(FieldDefinitionServiceInterface::class, [
            'getFieldDefinitionAdapter' => fn (Data $definition): ?AdapterInterface => $definition instanceof Checkbox
                ? null
                : $this->makeEmpty(AdapterInterface::class, ['getIndexMapping' => self::KEY_MAPPING]),
            'normalizeValue' => static fn (?Data $definition, mixed $value): mixed => $value,
        ]);
    }

    /**
     * The real relation adapters, so the mapping a key declares can be compared with the one a class
     * field of the same type declares.
     */
    private function createServiceWithRelationAdapters(): FieldDefinitionServiceInterface
    {
        $configService = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapterService = $this->makeEmpty(FieldDefinitionServiceInterface::class);

        return $this->makeEmpty(FieldDefinitionServiceInterface::class, [
            'getFieldDefinitionAdapter' => static function (Data $definition) use (
                $configService,
                $adapterService
            ): AdapterInterface {
                $adapter = $definition instanceof AdvancedManyToManyRelation
                    ? new AdvancedManyToManyRelationAdapter($configService, $adapterService)
                    : new RelationAdapter($configService, $adapterService);
                $adapter->setFieldDefinition($definition);

                return $adapter;
            },
        ]);
    }

    /**
     * The declared leaves of one group mapping, for the keys given as key id => localization flag.
     *
     * @param string[] $validLanguages
     * @param array<int, bool|null> $keyFlags
     */
    private function countDeclaredFieldsOfKeys(
        array $validLanguages,
        bool $fieldLocalized,
        array $keyFlags,
        mixed $keyDefinition = null,
        ?FieldDefinitionServiceInterface $fieldDefinitionService = null
    ): int {
        $relations = [];
        foreach ($keyFlags as $keyId => $localized) {
            $relations[] = $this->createRelation($keyId, self::GROUP_ID, 'k' . $keyId, $localized);
        }

        return $this->countDeclaredFields($this->getMappingForRelations(
            $this->createFieldDefinition($fieldLocalized),
            $validLanguages,
            $relations,
            null,
            $keyDefinition,
            $fieldDefinitionService
        ));
    }

    /**
     * @return string[]
     */
    private function getDeclaredLanguages(bool $fieldLocalized, ?bool $keyLocalized): array
    {
        return array_keys($this->getMappingForRelations(
            $this->createFieldDefinition($fieldLocalized),
            self::LANGUAGES,
            [$this->createRelation(self::KEY_ID, self::GROUP_ID, self::KEY_NAME, $keyLocalized)]
        ));
    }

    /**
     * @param string[] $validLanguages
     * @param KeyGroupRelation[] $relations
     */
    private function getMappingForRelations(
        Classificationstore $fieldDefinition,
        array $validLanguages,
        array $relations,
        ?AbstractLogger $logger = null,
        mixed $keyDefinition = null,
        ?FieldDefinitionServiceInterface $fieldDefinitionService = null
    ): array {
        $adapter = $this->createConfiguredAdapter(
            $validLanguages,
            $fieldDefinition,
            $logger,
            keyDefinition: $keyDefinition,
            fieldDefinitionService: $fieldDefinitionService
        );

        return (new ReflectionMethod(ClassificationStoreAdapter::class, 'getMappingForGroupConfig'))
            ->invoke($adapter, $relations, $this->getDeclarableLanguages($adapter));
    }

    /**
     * The store is stubbed to return the key id as the value of every key, so a document entry names the
     * key it was written from. The key configurations are held by the definition cache for the same
     * reason: the value path would otherwise read them from the database.
     *
     * @param string[] $validLanguages
     * @param KeyGroupRelation[] $relations
     */
    private function getDocumentValues(
        Classificationstore $fieldDefinition,
        array $validLanguages,
        array $relations,
        int $groupId = self::GROUP_ID,
        ?ClassificationstoreModel $store = null,
        mixed $keyDefinition = null,
        ?AbstractLogger $logger = null,
        ?FieldDefinitionServiceInterface $fieldDefinitionService = null
    ): array {
        $adapter = $this->createConfiguredAdapter(
            $validLanguages,
            $fieldDefinition,
            $logger,
            keyDefinition: $keyDefinition,
            fieldDefinitionService: $fieldDefinitionService
        );

        $store ??= $this->makeEmpty(ClassificationstoreModel::class, [
            'getLocalizedKeyValue' => static fn (int $group, int $keyId): int => $keyId,
        ]);

        return (new ReflectionMethod(ClassificationStoreAdapter::class, 'normalizeGroupValues'))
            ->invoke($adapter, $store, $groupId, $relations, $this->getDeclarableLanguages($adapter));
    }

    /**
     * @return string[]
     */
    private function getDeclarableLanguages(ClassificationStoreAdapter $adapter): array
    {
        return (new ReflectionMethod(ClassificationStoreAdapter::class, 'getValidLanguages'))->invoke($adapter);
    }

    private function countDeclaredFields(array $groupMapping): int
    {
        $declaredFields = 0;
        foreach ($groupMapping as $languageNode) {
            $declaredFields += $this->countLeaves($languageNode);
        }

        return $declaredFields;
    }

    /**
     * Every node that carries a type is one declared field: a scalar key is one, a relation three.
     */
    private function countLeaves(array $mapping): int
    {
        $leaves = isset($mapping['type']) ? 1 : 0;
        foreach ($mapping[self::PROPERTIES] ?? [] as $child) {
            $leaves += $this->countLeaves($child);
        }

        return $leaves;
    }

    private function createFieldDefinition(bool $localized): Classificationstore
    {
        $fieldDefinition = new Classificationstore();
        $fieldDefinition->setLocalized($localized);
        $fieldDefinition->setStoreId(self::STORE_ID);

        return $fieldDefinition;
    }

    /**
     * The localization flag is carried by the key configuration, which is what the resolver reads
     * through the definition cache, so every relation registers its key with the flag of the case.
     */
    private function createRelation(
        int $keyId,
        int $groupId,
        string $name,
        ?bool $localized = null
    ): KeyGroupRelation {
        $this->registerKey($keyId, $localized);

        $relation = new KeyGroupRelation();
        $relation->setKeyId($keyId);
        $relation->setGroupId($groupId);
        $relation->setName($name);
        $relation->setDefinition((string) json_encode(['fieldtype' => 'input', 'name' => $name]));

        return $relation;
    }

    private function registerKey(int $keyId, ?bool $localized): void
    {
        $keyConfig = (new KeyConfig())->setId($keyId);
        $keyConfig->setLocalized($localized);

        DefinitionCache::put($keyConfig);
    }

    private function excludeOnDefinition(KeyGroupRelation $relation): KeyGroupRelation
    {
        $relation->setDefinition((string) json_encode([
            'fieldtype' => 'input',
            'name' => $relation->getName(),
            self::DEFINITION_INDEXED => false,
        ]));

        return $relation;
    }

    /**
     * @param string[] $validLanguages
     */
    private function createConfiguredAdapter(
        array $validLanguages,
        ?Classificationstore $fieldDefinition = null,
        ?AbstractLogger $logger = null,
        ?RuntimeCacheResolverInterface $runtimeCacheResolver = null,
        mixed $keyDefinition = null,
        ?FieldDefinitionServiceInterface $fieldDefinitionService = null
    ): ClassificationStoreAdapter {
        $adapter = new ClassificationStoreAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $fieldDefinitionService ?? $this->makeEmpty(FieldDefinitionServiceInterface::class, [
                'getFieldDefinitionAdapter' => $this->makeEmpty(AdapterInterface::class, [
                    'getIndexMapping' => self::KEY_MAPPING,
                ]),
                'normalizeValue' => static fn (?Data $fieldDefinition, mixed $value): mixed => $value,
            ])
        );
        $adapter->setClassificationService($this->makeEmpty(ServiceResolverInterface::class, [
            'getFieldDefinitionFromKeyConfig' => $keyDefinition ?? new Input(),
        ]));
        $adapter->setLanguageService($this->makeEmpty(LanguageServiceInterface::class, [
            'getValidLanguages' => $validLanguages,
        ]));
        $adapter->setKeyLocaleService($this->createKeyLocaleService());
        $adapter->setFieldDefinition($fieldDefinition ?? new Classificationstore());
        $adapter->setLogger($logger ?? $this->createCollectingLogger());
        $adapter->setRuntimeCacheResolver(
            $runtimeCacheResolver ?? $this->createRuntimeCacheResolver(new ArrayObject())
        );

        return $adapter;
    }

    /**
     * An in-memory stand-in for the runtime cache. A case that is about the memo itself passes one
     * instance to several adapters, which is what the runtime cache is for the adapters normalize()
     * builds per object; every other case gets its own, so no memo crosses a test.
     */
    private function createRuntimeCacheResolver(ArrayObject $entries): RuntimeCacheResolverInterface
    {
        return $this->makeEmpty(RuntimeCacheResolverInterface::class, [
            'isRegistered' => static fn (string $index): bool => $entries->offsetExists($index),
            'load' => static fn (string $id): mixed => $entries[$id],
            'save' => static function (mixed $data, string $id) use ($entries): void {
                $entries[$id] = $data;
            },
        ]);
    }

    /**
     * The real resolver, not a stub of it: the rule under test is which languages the adapter asks it
     * for, so a stubbed answer would assert the test's own arithmetic instead.
     */
    private function createKeyLocaleService(): KeyLocaleServiceInterface
    {
        return new KeyLocaleService(new LocalizationResolver($this->makeEmpty(Connection::class)));
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
