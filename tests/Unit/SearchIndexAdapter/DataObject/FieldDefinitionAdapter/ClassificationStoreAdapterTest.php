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
use Doctrine\DBAL\Connection;
use Exception;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\ClassificationStoreAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\KeyLocaleService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\KeyLocaleServiceInterface;
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
use Pimcore\Model\DataObject\Classificationstore\LocalizationResolver;
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

    private const OTHER_KEY_ID = 43;

    private const THIRD_KEY_ID = 44;

    private const FOURTH_KEY_ID = 45;

    private const GROUP_ID = 9;

    private const OTHER_GROUP_ID = 10;

    private const FIELD_NAME = 'csstore';

    private const GROUP_NAME = 'Specifications';

    private const DEFINITION_INDEXED = 'indexed';

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
     * The declared leaves of one group mapping, for the keys given as key id => localization flag.
     *
     * @param string[] $validLanguages
     * @param array<int, bool|null> $keyFlags
     */
    private function countDeclaredFieldsOfKeys(array $validLanguages, bool $fieldLocalized, array $keyFlags): int
    {
        $relations = [];
        foreach ($keyFlags as $keyId => $localized) {
            $relations[] = $this->createRelation($keyId, self::GROUP_ID, 'k' . $keyId, $localized);
        }

        return $this->countDeclaredFields($this->getMappingForRelations(
            $this->createFieldDefinition($fieldLocalized),
            $validLanguages,
            $relations
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
        ?AbstractLogger $logger = null
    ): array {
        $adapter = $this->createConfiguredAdapter($validLanguages, $fieldDefinition, $logger);

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
        int $groupId = self::GROUP_ID
    ): array {
        $adapter = $this->createConfiguredAdapter($validLanguages, $fieldDefinition);

        $store = $this->makeEmpty(ClassificationstoreModel::class, [
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
            $declaredFields += count($languageNode[self::PROPERTIES]);
        }

        return $declaredFields;
    }

    private function createFieldDefinition(bool $localized): Classificationstore
    {
        $fieldDefinition = new Classificationstore();
        $fieldDefinition->setLocalized($localized);

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
        $adapter->setKeyLocaleService($this->createKeyLocaleService());
        $adapter->setFieldDefinition($fieldDefinition ?? new Classificationstore());
        $adapter->setLogger($logger ?? $this->createCollectingLogger());

        return $adapter;
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
