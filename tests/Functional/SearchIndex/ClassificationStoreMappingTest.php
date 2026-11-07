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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\SearchIndex;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\BooleanFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IntegerFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\BooleanMultiSelectFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\ClassificationStoreFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\MappingProperty;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexUpdateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Classificationstore as ClassificationstoreField;
use Pimcore\Model\DataObject\ClassDefinition\Layout\Panel;
use Pimcore\Model\DataObject\ClassDefinition\Layout\Tabpanel;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation;
use Pimcore\Model\DataObject\Classificationstore\StoreConfig;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * The classification store writes <field>.<group>.<language>.<key> for the languages each key is kept
 * in, so the mapping has to declare a node per language under every mapped group - carrying only the
 * keys that language holds - and the nested root has to refuse dynamic mapping for everything that is
 * not declared.
 */
class ClassificationStoreMappingTest extends Unit
{
    private const CLASS_NAME = 'csMapping';

    private const NON_LOCALIZED_CLASS_NAME = 'csMappingPerKey';

    private const FIELD_NAME = 'csstore';

    private const GROUP_NAME = 'Specifications';

    private const SECOND_GROUP_NAME = 'Dimensions';

    private const KEY_NAME = 'Size';

    private const NON_LOCALIZED_KEY_NAME = 'Depth';

    private const LOCALIZED_KEY_NAME = 'Colour';

    private const EXCLUDED_KEY_NAME = 'Weight';

    /**
     * A text key, which is the kind that carries multi-fields: getMappingForTextKeyword() gives it a
     * "keyword" and a "sort" sub-field under "fields". It is flagged not localized, so it is declared
     * under the non-localized node only, in both classes.
     */
    private const TEXT_KEY_NAME = 'Material';

    private const TEXT_KEY_TYPE = 'input';

    private const TEXT_KEY_VALUE = 'carbon';

    private const KEYWORD_MULTI_FIELD = 'keyword';

    private const SORT_MULTI_FIELD = 'sort';

    private const KEY_VALUE = 42;

    private const NON_LOCALIZED_KEY_VALUE = 17;

    private const NON_LOCALIZED_KEY_LANGUAGE_VALUE = 99;

    private const LOCALIZED_KEY_VALUE = 7;

    private const EXCLUDED_KEY_VALUE = 23;

    private const KEY_TYPE = 'numeric';

    private const DEFINITION_INDEXED = 'indexed';

    private const PROPERTIES = 'properties';

    /**
     * @var \Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester
     */
    protected $tester;

    private StoreConfig $storeConfig;

    private GroupConfig $groupConfig;

    private GroupConfig $secondGroupConfig;

    private KeyConfig $keyConfig;

    private KeyConfig $nonLocalizedKeyConfig;

    private KeyConfig $localizedKeyConfig;

    private KeyConfig $excludedKeyConfig;

    private KeyConfig $textKeyConfig;

    private ClassDefinition $classDefinition;

    private ?ClassDefinition $nonLocalizedClassDefinition = null;

    protected function _before(): void
    {
        $this->tester->enableSynchronousProcessing();
        $this->tester->clearQueue();
        $this->createStoreDefinitions();
        $this->classDefinition = $this->createClassDefinition(self::CLASS_NAME, true);
        $this->recreateClassIndex($this->classDefinition);
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
        $this->classDefinition->delete();
        $this->nonLocalizedClassDefinition?->delete();
        $this->deleteKeyWithAssignment($this->keyConfig);
        $this->deleteKeyWithAssignment($this->nonLocalizedKeyConfig);
        $this->deleteKeyWithAssignment($this->localizedKeyConfig);
        $this->deleteKeyWithAssignment($this->excludedKeyConfig);
        $this->deleteKeyWithAssignment($this->textKeyConfig);
        $this->groupConfig->delete();
        $this->secondGroupConfig->delete();
        $this->storeConfig->delete();
    }

    public function testEveryValidLanguageIsDeclaredAndTheNestedRootRefusesDynamicMapping(): void
    {
        $fieldMapping = $this->getStoreFieldMapping();

        // The engine echoes the dynamic setting back as the string "false".
        $this->assertArrayHasKey('dynamic', $fieldMapping);
        $this->assertFalse(filter_var($fieldMapping['dynamic'], FILTER_VALIDATE_BOOLEAN));

        $groupMapping = $fieldMapping[self::PROPERTIES][self::GROUP_NAME][self::PROPERTIES];

        // The engine does not promise an order for the properties of a mapping response.
        $this->assertEqualsCanonicalizing($this->getDeclaredLanguages(), array_keys($groupMapping));

        foreach ($groupMapping as $language => $languageMapping) {
            $this->assertArrayHasKey(self::KEY_NAME, $languageMapping[self::PROPERTIES], $language);
        }
    }

    /**
     * The language nodes a group declares are per key: the key that inherits the localized field is
     * declared under the non-localized node and under every valid language, the key flagged as not
     * localized only under the non-localized node, and the key excluded on its own definition under
     * none of them. The index is built from scratch in _before(), so this is the mapping the bundle
     * declares, not one dynamic mapping contributed to.
     *
     * The non-localized key holds a value in a real language as well, so its absence from the language
     * nodes of _source is the document not writing it there, not there being nothing to write.
     */
    public function testThePerKeyLanguageSetIsDeclaredAndWrittenOnARecreatedIndex(): void
    {
        $language = $this->getValidLanguages()[0];
        $object = $this->createObjectWithStoreValue($language, self::CLASS_NAME);

        $groupMapping = $this->getGroupMapping(self::CLASS_NAME);

        // asserted before the loop, so an empty group node cannot satisfy the absence assertions vacuously
        $this->assertEqualsCanonicalizing($this->getDeclaredLanguages(), array_keys($groupMapping));

        $this->assertEqualsCanonicalizing(
            [self::KEY_NAME, self::NON_LOCALIZED_KEY_NAME, self::LOCALIZED_KEY_NAME, self::TEXT_KEY_NAME],
            array_keys($groupMapping[MappingProperty::NOT_LOCALIZED_KEY][self::PROPERTIES])
        );

        foreach ($this->getValidLanguages() as $validLanguage) {
            $this->assertEqualsCanonicalizing(
                [self::KEY_NAME, self::LOCALIZED_KEY_NAME],
                array_keys($groupMapping[$validLanguage][self::PROPERTIES]),
                $validLanguage
            );
        }

        $groupSource = $this->getGroupSource($object, self::CLASS_NAME);

        $this->assertEquals(self::KEY_VALUE, $groupSource[$language][self::KEY_NAME]);
        $this->assertEquals(
            self::NON_LOCALIZED_KEY_VALUE,
            $groupSource[MappingProperty::NOT_LOCALIZED_KEY][self::NON_LOCALIZED_KEY_NAME]
        );

        foreach ($groupSource as $sourceLanguage => $keyValues) {
            $this->assertArrayNotHasKey(self::EXCLUDED_KEY_NAME, $keyValues, $sourceLanguage);

            if ($sourceLanguage !== MappingProperty::NOT_LOCALIZED_KEY) {
                $this->assertArrayNotHasKey(self::NON_LOCALIZED_KEY_NAME, $keyValues, $sourceLanguage);
            }
        }
    }

    /**
     * The combination the per key flag exists for, and the one the class definition field's flag alone
     * cannot express: a field that is not localized, carrying a key that is flagged localized. Every
     * other key of the group is declared and written under the non-localized node only, that one key
     * under every valid language - on an index built from scratch, so this is the declared mapping.
     *
     * The flag is resolved per key - LocalizationResolver::isKeyLocalized() - so the field's own flag
     * being false does not override a key that carries one, and the non-localized key of the same group
     * holds a value in a real language too, so its absence under that language is asserted, not vacuous.
     */
    public function testAKeyFlaggedLocalizedDeclaresTheLanguagesOnANonLocalizedField(): void
    {
        $this->nonLocalizedClassDefinition = $this->createClassDefinition(self::NON_LOCALIZED_CLASS_NAME, false);
        $this->recreateClassIndex($this->nonLocalizedClassDefinition);

        $language = $this->getValidLanguages()[0];
        $object = $this->createObjectWithStoreValue($language, self::NON_LOCALIZED_CLASS_NAME);

        $groupMapping = $this->getGroupMapping(self::NON_LOCALIZED_CLASS_NAME);

        // the one localized key is what makes the languages part of the mapping of a non-localized field
        $this->assertEqualsCanonicalizing($this->getDeclaredLanguages(), array_keys($groupMapping));

        $this->assertEqualsCanonicalizing(
            [self::KEY_NAME, self::NON_LOCALIZED_KEY_NAME, self::LOCALIZED_KEY_NAME, self::TEXT_KEY_NAME],
            array_keys($groupMapping[MappingProperty::NOT_LOCALIZED_KEY][self::PROPERTIES])
        );

        foreach ($this->getValidLanguages() as $validLanguage) {
            $this->assertSame(
                [self::LOCALIZED_KEY_NAME],
                array_keys($groupMapping[$validLanguage][self::PROPERTIES]),
                $validLanguage
            );
        }

        $groupSource = $this->getGroupSource($object, self::NON_LOCALIZED_CLASS_NAME);

        $this->assertEquals(self::LOCALIZED_KEY_VALUE, $groupSource[$language][self::LOCALIZED_KEY_NAME]);
        $this->assertSame([self::LOCALIZED_KEY_NAME], array_keys($groupSource[$language]));
        $this->assertEquals(
            self::NON_LOCALIZED_KEY_VALUE,
            $groupSource[MappingProperty::NOT_LOCALIZED_KEY][self::NON_LOCALIZED_KEY_NAME]
        );

        foreach ($groupSource as $sourceLanguage => $keyValues) {
            $this->assertArrayNotHasKey(self::EXCLUDED_KEY_NAME, $keyValues, $sourceLanguage);
        }
    }

    /**
     * The index exclusion belongs to the key, not to one of its assignments: the key excluded on its
     * own definition is declared in neither of the two groups it is assigned to, while the key assigned
     * to the same two groups without the flag is declared in both. Neither assignment carries a
     * definition of its own, so what reaches the mapping is the key's, through the relation listing.
     */
    public function testAKeyExcludedOnItsDefinitionIsAbsentFromEveryGroupItIsAssignedTo(): void
    {
        $groupMappings = $this->getStoreFieldMapping()[self::PROPERTIES];

        foreach ([self::GROUP_NAME, self::SECOND_GROUP_NAME] as $groupName) {
            $declaredKeys = array_keys(
                $groupMappings[$groupName][self::PROPERTIES][MappingProperty::NOT_LOCALIZED_KEY][self::PROPERTIES]
            );

            $this->assertContains(self::KEY_NAME, $declaredKeys, $groupName);
            $this->assertNotContains(self::EXCLUDED_KEY_NAME, $declaredKeys, $groupName);
        }
    }

    public function testAValueInANonDefaultLanguageIsFilterable(): void
    {
        $language = $this->getValidLanguages()[0];
        $object = $this->createObjectWithStoreValue($language, self::CLASS_NAME);

        $this->assertSame(
            [$object->getId()],
            $this->filterByKey(self::KEY_NAME, self::KEY_VALUE, $language),
            $language
        );
    }

    /**
     * The read side of the per key language set: the filter takes its locale from the caller, so a
     * non-localized key is only found under the node the mapping declares for it. The group holds keys
     * of both kinds, which is why the locale has to be chosen per key and not per group.
     */
    public function testANonLocalizedKeyIsFilterableUnderTheDefaultLocaleOnly(): void
    {
        $language = $this->getValidLanguages()[0];
        $object = $this->createObjectWithStoreValue($language, self::CLASS_NAME);

        $this->assertSame(
            [$object->getId()],
            $this->filterByKey(
                self::NON_LOCALIZED_KEY_NAME,
                self::NON_LOCALIZED_KEY_VALUE,
                MappingProperty::NOT_LOCALIZED_KEY
            )
        );

        $this->assertSame(
            [],
            $this->filterByKey(self::NON_LOCALIZED_KEY_NAME, self::NON_LOCALIZED_KEY_VALUE, $language),
            $language
        );
    }

    /**
     * The same guarantee for a filter that asks for a *missing* value, which is the case an equality
     * filter cannot stand in for. Equality gives the empty result on a node the key is not indexed
     * under by itself - there is nothing there to equal - but a missing-value filter is `must_not
     * exists`, and an absent path satisfies it: before the locale was checked against the mapping this
     * returned every element that has the group at all, including the one whose value sits under the
     * node the key really is indexed under.
     *
     * The second assertion is the control. The same filter on a node the key *is* declared under still
     * finds the element that has no value there, so the empty result above is the wrong node being
     * ruled out and not missing-value filtering having stopped working inside the store.
     */
    public function testAMissingValueFilterOnAWrongLocaleFindsNothingRatherThanEverything(): void
    {
        $language = $this->getValidLanguages()[0];
        $object = $this->createObjectWithStoreValue($language, self::CLASS_NAME);

        // the non-localized key is declared under "default" only, and holds its value there
        $this->assertSame(
            [],
            $this->filterByMissingKey(self::NON_LOCALIZED_KEY_NAME, $language),
            $language
        );

        // the localized key is declared under "default" too, and the object wrote it under $language only
        $this->assertSame(
            [$object->getId()],
            $this->filterByMissingKey(self::KEY_NAME, MappingProperty::NOT_LOCALIZED_KEY)
        );

        // the other sub-modifier that negates existence: a null in a BooleanMultiSelectFilter's values
        $this->assertSame(
            [],
            $this->filterByStore(new BooleanMultiSelectFilter(self::NON_LOCALIZED_KEY_NAME, [null], false), $language),
            $language
        );
        $this->assertSame(
            [$object->getId()],
            $this->filterByStore(
                new BooleanMultiSelectFilter(self::KEY_NAME, [null], false),
                MappingProperty::NOT_LOCALIZED_KEY
            )
        );
    }

    /**
     * A search that names no class reads the global alias, so every class index is behind it at once
     * and whether the addressed node is declared is a per index answer. The two classes here differ in
     * exactly the way the per key flag makes possible: the same key inherits the localized field in
     * one and is not localized in the other, so the language node of that key exists in the first
     * class's index only.
     *
     * A missing-value filter in that language must therefore not reach the second class's objects. The
     * address does not exist for them - the language node is there, because another key of the group
     * is localized, but this key is not declared under it - and an absent path is what `must_not
     * exists` *satisfies*. So before the declaring indices were preserved, one index declaring the
     * address disabled the guard for the other and this returned the second class's object: the one
     * whose value sits under the node its own key really is indexed under, which is the opposite of
     * the promise.
     *
     * The second assertion is the control, on the node both indices declare: the first class's object
     * has no value there and is still found, and the second class's object - which does have one - is
     * still excluded. So the narrowing above rules out the indices that cannot answer, and does not
     * stop the other indices from answering.
     */
    public function testAMissingValueFilterAcrossClassesSkipsTheClassesThatDoNotDeclareTheLocale(): void
    {
        $this->nonLocalizedClassDefinition = $this->createClassDefinition(self::NON_LOCALIZED_CLASS_NAME, false);
        $this->recreateClassIndex($this->nonLocalizedClassDefinition);

        $language = $this->getValidLanguages()[0];
        $localizedObject = $this->createObjectWithStoreValue($language, self::CLASS_NAME);
        $nonLocalizedObject = $this->createObjectWithStoreValue($language, self::NON_LOCALIZED_CLASS_NAME);

        // the non-localized class holds this key's value under "default", and declares no node for $language
        $nonLocalizedSource = $this->getGroupSource($nonLocalizedObject, self::NON_LOCALIZED_CLASS_NAME);
        $this->assertEquals(
            self::KEY_VALUE,
            $nonLocalizedSource[MappingProperty::NOT_LOCALIZED_KEY][self::KEY_NAME]
        );
        // the language node itself exists in that class - another key of the group is localized - but
        // this key is not declared under it, which is the address the filter below builds
        $this->assertArrayNotHasKey(
            self::KEY_NAME,
            $this->getGroupMapping(self::NON_LOCALIZED_CLASS_NAME)[$language][self::PROPERTIES],
            $language
        );

        $this->assertSame(
            [],
            $this->filterByStoreAcrossClasses(new BooleanFilter(self::KEY_NAME, null, false), $language),
            $language
        );

        $this->assertSame(
            [$localizedObject->getId()],
            $this->filterByStoreAcrossClasses(
                new BooleanFilter(self::KEY_NAME, null, false),
                MappingProperty::NOT_LOCALIZED_KEY
            )
        );
    }

    /**
     * A key's mapping is not only the node named after the key. A text key declares its sub-fields
     * under "fields" - getMappingForTextKeyword() gives every one of them a "keyword" and a "sort"
     * multi-field - and a filter may address those, so a path ending in one is declared and has to be
     * filtered on rather than ruled out. Reading "properties" alone reported it undeclared and turned
     * the filter into match_none.
     *
     * The second half is the control, in both directions: the same filter finds nothing once the key
     * holds a value, so the result above is missing-value filtering on the multi-field and not a
     * clause that matches everything; and a sub-field the mapping does not declare is still ruled out,
     * so traversing "fields" did not make the guard accept any suffix at all.
     */
    public function testAMissingValueFilterOnAMappedMultiFieldIsAnsweredRatherThanRuledOut(): void
    {
        $object = $this->createObjectWithStoreValue($this->getValidLanguages()[0], self::CLASS_NAME);

        foreach ([self::KEYWORD_MULTI_FIELD, self::SORT_MULTI_FIELD] as $multiField) {
            $this->assertSame(
                [$object->getId()],
                $this->filterByMissingKey(
                    self::TEXT_KEY_NAME . '.' . $multiField,
                    MappingProperty::NOT_LOCALIZED_KEY
                ),
                $multiField
            );
        }

        $this->assertSame(
            [],
            $this->filterByMissingKey(
                self::TEXT_KEY_NAME . '.notAMultiField',
                MappingProperty::NOT_LOCALIZED_KEY
            )
        );

        $this->setTextKeyValue($object);

        $this->assertSame(
            [],
            $this->filterByMissingKey(
                self::TEXT_KEY_NAME . '.' . self::KEYWORD_MULTI_FIELD,
                MappingProperty::NOT_LOCALIZED_KEY
            )
        );
    }

    /**
     * @return int[]
     */
    private function filterByKey(string $keyName, int $value, string $locale): array
    {
        return $this->filterByStore(new IntegerFilter($keyName, $value, false), $locale);
    }

    /**
     * A BooleanFilter with a null search term is the bundle's "has no value" filter, whatever the type
     * of the key - it becomes `must_not exists` on the key's path.
     *
     * @return int[]
     */
    private function filterByMissingKey(string $keyName, string $locale): array
    {
        return $this->filterByStore(new BooleanFilter($keyName, null, false), $locale);
    }

    /**
     * @return int[]
     */
    private function filterByStore(
        BooleanFilter|BooleanMultiSelectFilter|IntegerFilter $subModifier,
        string $locale
    ): array {
        return $this->runStoreSearch($subModifier, $locale, $this->classDefinition);
    }

    /**
     * The same filter without setClassDefinition(), which is what a classless data object search is:
     * it resolves to the global alias, so every class index is behind it at once.
     *
     * @return int[]
     */
    private function filterByStoreAcrossClasses(
        BooleanFilter|BooleanMultiSelectFilter|IntegerFilter $subModifier,
        string $locale
    ): array {
        return $this->runStoreSearch($subModifier, $locale, null);
    }

    /**
     * @return int[]
     */
    private function runStoreSearch(
        BooleanFilter|BooleanMultiSelectFilter|IntegerFilter $subModifier,
        string $locale,
        ?ClassDefinition $classDefinition
    ): array {
        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $search = $searchProvider
            ->createDataObjectSearch()
            ->addModifier(new ClassificationStoreFilter(
                self::FIELD_NAME,
                self::GROUP_NAME,
                $subModifier,
                $locale
            ));

        if ($classDefinition !== null) {
            $search->setClassDefinition($classDefinition);
        }

        return $searchService->search($search)->getIds();
    }

    /**
     * @return string[]
     */
    private function getDeclaredLanguages(): array
    {
        return array_merge([MappingProperty::NOT_LOCALIZED_KEY], $this->getValidLanguages());
    }

    private function getStoreFieldMapping(string $className = self::CLASS_NAME): array
    {
        $indexName = $this->tester->getIndexName($className, true);
        $mapping = $this->tester->getIndexMapping($indexName);
        $properties = $mapping[$indexName]['mappings'][self::PROPERTIES];

        return $properties[FieldCategory::STANDARD_FIELDS->value][self::PROPERTIES][self::FIELD_NAME];
    }

    /**
     * The language nodes the group declares, keyed by language.
     */
    private function getGroupMapping(string $className): array
    {
        return $this->getStoreFieldMapping($className)[self::PROPERTIES][self::GROUP_NAME][self::PROPERTIES];
    }

    /**
     * The values the group holds in _source, keyed by language.
     */
    private function getGroupSource(Concrete $object, string $className): array
    {
        $indexName = $this->tester->getIndexName($className, true);
        $source = $this->tester->checkIndexEntry($object->getId(), $indexName)['_source'];

        return $source[FieldCategory::STANDARD_FIELDS->value][self::FIELD_NAME][self::GROUP_NAME];
    }

    /**
     * @return string[]
     */
    private function getValidLanguages(): array
    {
        /** @var LanguageServiceInterface $languageService */
        $languageService = $this->tester->grabService(LanguageServiceInterface::class);

        return $languageService->getValidLanguages();
    }

    private function createObjectWithStoreValue(string $language, string $classDefinitionName): Concrete
    {
        $className = 'Pimcore\\Model\\DataObject\\' . ucfirst($classDefinitionName);
        /** @var Concrete $object */
        $object = new $className();
        $object->setParentId(1);
        $object->setKey(uniqid('cs-mapping-'));
        $object->setPublished(true);

        $store = $object->get(self::FIELD_NAME);
        $store->setActiveGroups([$this->groupConfig->getId() => true]);
        $store->setLocalizedKeyValue(
            $this->groupConfig->getId(),
            $this->keyConfig->getId(),
            self::KEY_VALUE,
            $language
        );
        $store->setLocalizedKeyValue(
            $this->groupConfig->getId(),
            $this->nonLocalizedKeyConfig->getId(),
            self::NON_LOCALIZED_KEY_VALUE,
            MappingProperty::NOT_LOCALIZED_KEY
        );
        // the store keeps this one too, so an absent language entry is a choice and not an empty value
        $store->setLocalizedKeyValue(
            $this->groupConfig->getId(),
            $this->nonLocalizedKeyConfig->getId(),
            self::NON_LOCALIZED_KEY_LANGUAGE_VALUE,
            $language
        );
        $store->setLocalizedKeyValue(
            $this->groupConfig->getId(),
            $this->localizedKeyConfig->getId(),
            self::LOCALIZED_KEY_VALUE,
            $language
        );
        $store->setLocalizedKeyValue(
            $this->groupConfig->getId(),
            $this->excludedKeyConfig->getId(),
            self::EXCLUDED_KEY_VALUE,
            $language
        );
        $object->save();
        $this->tester->flushIndex();

        return $object;
    }

    /**
     * The text key is written under the non-localized node, which is the only node it is declared
     * under - it carries localized = false.
     */
    private function setTextKeyValue(Concrete $object): void
    {
        $store = $object->get(self::FIELD_NAME);
        $store->setLocalizedKeyValue(
            $this->groupConfig->getId(),
            $this->textKeyConfig->getId(),
            self::TEXT_KEY_VALUE,
            MappingProperty::NOT_LOCALIZED_KEY
        );
        $object->save();
        $this->tester->flushIndex();
    }

    private function createStoreDefinitions(): void
    {
        $this->storeConfig = new StoreConfig();
        $this->storeConfig->setName('gdiClassificationStoreMapping');
        $this->storeConfig->save();

        $this->groupConfig = $this->createGroup(self::GROUP_NAME);
        $this->secondGroupConfig = $this->createGroup(self::SECOND_GROUP_NAME);

        $this->keyConfig = $this->createKeyWithAssignment(self::KEY_NAME);
        $this->nonLocalizedKeyConfig = $this->createKeyWithAssignment(self::NON_LOCALIZED_KEY_NAME, false);
        $this->localizedKeyConfig = $this->createKeyWithAssignment(self::LOCALIZED_KEY_NAME, true);
        $this->excludedKeyConfig = $this->createKeyWithAssignment(self::EXCLUDED_KEY_NAME, indexed: false);
        $this->textKeyConfig = $this->createKeyWithAssignment(
            self::TEXT_KEY_NAME,
            false,
            type: self::TEXT_KEY_TYPE
        );

        // the same two keys are assigned to a second group, so the exclusion is exercised per key
        $this->assignKeyToGroup($this->keyConfig, $this->secondGroupConfig);
        $this->assignKeyToGroup($this->excludedKeyConfig, $this->secondGroupConfig);
    }

    private function createGroup(string $name): GroupConfig
    {
        $groupConfig = new GroupConfig();
        $groupConfig->setStoreId($this->storeConfig->getId());
        $groupConfig->setName($name);
        $groupConfig->save();

        return $groupConfig;
    }

    /**
     * The indexed key carries neither flag, the way every key does that was created before they existed:
     * it inherits the localized flag of the class definition field and is indexed.
     */
    private function createKeyWithAssignment(
        string $name,
        ?bool $localized = null,
        bool $indexed = true,
        string $type = self::KEY_TYPE
    ): KeyConfig {
        $definition = [
            'fieldtype' => $type,
            'name' => $name,
            'title' => $name,
        ];
        if ($type === self::KEY_TYPE) {
            $definition['integer'] = true;
        }
        if (!$indexed) {
            $definition[self::DEFINITION_INDEXED] = false;
        }
        $keyDefinition = (string) json_encode($definition);

        $keyConfig = new KeyConfig();
        $keyConfig->setStoreId($this->storeConfig->getId());
        $keyConfig->setName($name);
        $keyConfig->setType($type);
        $keyConfig->setDefinition($keyDefinition);
        $keyConfig->setLocalized($localized);
        $keyConfig->setEnabled(true);
        $keyConfig->save();

        $this->assignKeyToGroup($keyConfig, $this->groupConfig);

        return $keyConfig;
    }

    /**
     * The assignment carries no definition of its own - the relation table has no such column, and the
     * relation listing hands over the key's. Setting one here would hide which of the two the index
     * exclusion is read from.
     */
    private function assignKeyToGroup(KeyConfig $keyConfig, GroupConfig $groupConfig): void
    {
        $relation = new KeyGroupRelation();
        $relation->setKeyId($keyConfig->getId());
        $relation->setGroupId($groupConfig->getId());
        $relation->setName($keyConfig->getName());
        $relation->setType($keyConfig->getType());
        $relation->setEnabled(true);
        $relation->save();
    }

    private function deleteKeyWithAssignment(KeyConfig $keyConfig): void
    {
        foreach ([$this->groupConfig, $this->secondGroupConfig] as $groupConfig) {
            $relation = new KeyGroupRelation();
            $relation->setKeyId($keyConfig->getId());
            $relation->setGroupId($groupConfig->getId());
            $relation->delete();
        }

        $keyConfig->delete();
    }

    private function createClassDefinition(string $name, bool $localized): ClassDefinition
    {
        $field = new ClassificationstoreField();
        $field->setName(self::FIELD_NAME);
        $field->setStoreId($this->storeConfig->getId());
        $field->setLocalized($localized);

        $panel = (new Panel())->setName('MyLayout');
        $panel->addChild($field);
        $tabPanel = (new Tabpanel())->setName('Layout');
        $tabPanel->addChild($panel);
        $root = new Panel();
        $root->addChild($tabPanel);

        $classDefinition = new ClassDefinition();
        $classDefinition->setName($name);
        $classDefinition->setLayoutDefinitions($root);
        $classDefinition->save();

        return $classDefinition;
    }

    /**
     * Other tests leave dynamically mapped fields behind in the shared indices, so the index of this
     * class is created from scratch to get the mapping the bundle itself declares.
     */
    private function recreateClassIndex(ClassDefinition $classDefinition): void
    {
        /** @var IndexUpdateServiceInterface $indexUpdateService */
        $indexUpdateService = $this->tester->grabService(IndexUpdateServiceInterface::class);
        $indexUpdateService
            ->setReCreateIndex(true)
            ->updateClassDefinition($classDefinition);
        $this->tester->flushIndex();
    }
}
