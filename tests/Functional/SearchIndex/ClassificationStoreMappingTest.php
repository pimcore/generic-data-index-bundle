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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IntegerFilter;
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

    private const FIELD_NAME = 'csstore';

    private const GROUP_NAME = 'Specifications';

    private const KEY_NAME = 'Size';

    private const NON_LOCALIZED_KEY_NAME = 'Depth';

    private const EXCLUDED_KEY_NAME = 'Weight';

    private const KEY_VALUE = 42;

    private const NON_LOCALIZED_KEY_VALUE = 17;

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

    private KeyConfig $keyConfig;

    private KeyConfig $nonLocalizedKeyConfig;

    private KeyConfig $excludedKeyConfig;

    private ClassDefinition $classDefinition;

    protected function _before(): void
    {
        $this->tester->enableSynchronousProcessing();
        $this->tester->clearQueue();
        $this->createStoreDefinitions();
        $this->classDefinition = $this->createClassDefinition();
        $this->recreateClassIndex();
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
        $this->classDefinition->delete();
        $this->deleteKeyWithAssignment($this->keyConfig);
        $this->deleteKeyWithAssignment($this->nonLocalizedKeyConfig);
        $this->deleteKeyWithAssignment($this->excludedKeyConfig);
        $this->groupConfig->delete();
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
     */
    public function testThePerKeyLanguageSetIsDeclaredAndWrittenOnARecreatedIndex(): void
    {
        $language = $this->getValidLanguages()[0];
        $object = $this->createObjectWithStoreValue($language);

        $groupMapping = $this->getStoreFieldMapping()[self::PROPERTIES][self::GROUP_NAME][self::PROPERTIES];

        // asserted before the loop, so an empty group node cannot satisfy the absence assertions vacuously
        $this->assertEqualsCanonicalizing($this->getDeclaredLanguages(), array_keys($groupMapping));

        $this->assertEqualsCanonicalizing(
            [self::KEY_NAME, self::NON_LOCALIZED_KEY_NAME],
            array_keys($groupMapping[MappingProperty::NOT_LOCALIZED_KEY][self::PROPERTIES])
        );

        foreach ($this->getValidLanguages() as $validLanguage) {
            $this->assertSame(
                [self::KEY_NAME],
                array_keys($groupMapping[$validLanguage][self::PROPERTIES]),
                $validLanguage
            );
        }

        $indexName = $this->tester->getIndexName(self::CLASS_NAME, true);
        $source = $this->tester->checkIndexEntry($object->getId(), $indexName)['_source'];
        $groupSource = $source[FieldCategory::STANDARD_FIELDS->value][self::FIELD_NAME][self::GROUP_NAME];

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

    public function testAValueInANonDefaultLanguageIsFilterable(): void
    {
        $language = $this->getValidLanguages()[0];
        $object = $this->createObjectWithStoreValue($language);

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $result = $searchService->search(
            $searchProvider
                ->createDataObjectSearch()
                ->addModifier(new ClassificationStoreFilter(
                    self::FIELD_NAME,
                    self::GROUP_NAME,
                    new IntegerFilter(self::KEY_NAME, self::KEY_VALUE, false),
                    $language
                ))
                ->setClassDefinition($this->classDefinition)
        );

        $this->assertSame([$object->getId()], $result->getIds(), $language);
    }

    /**
     * @return string[]
     */
    private function getDeclaredLanguages(): array
    {
        return array_merge([MappingProperty::NOT_LOCALIZED_KEY], $this->getValidLanguages());
    }

    private function getStoreFieldMapping(): array
    {
        $indexName = $this->tester->getIndexName(self::CLASS_NAME, true);
        $mapping = $this->tester->getIndexMapping($indexName);
        $properties = $mapping[$indexName]['mappings'][self::PROPERTIES];

        return $properties[FieldCategory::STANDARD_FIELDS->value][self::PROPERTIES][self::FIELD_NAME];
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

    private function createObjectWithStoreValue(string $language): Concrete
    {
        $className = 'Pimcore\\Model\\DataObject\\' . ucfirst(self::CLASS_NAME);
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

    private function createStoreDefinitions(): void
    {
        $this->storeConfig = new StoreConfig();
        $this->storeConfig->setName('gdiClassificationStoreMapping');
        $this->storeConfig->save();

        $this->groupConfig = new GroupConfig();
        $this->groupConfig->setStoreId($this->storeConfig->getId());
        $this->groupConfig->setName(self::GROUP_NAME);
        $this->groupConfig->save();

        $this->keyConfig = $this->createKeyWithAssignment(self::KEY_NAME);
        $this->nonLocalizedKeyConfig = $this->createKeyWithAssignment(self::NON_LOCALIZED_KEY_NAME, false);
        $this->excludedKeyConfig = $this->createKeyWithAssignment(self::EXCLUDED_KEY_NAME, indexed: false);
    }

    /**
     * The indexed key carries neither flag, the way every key does that was created before they existed:
     * it inherits the localized flag of the class definition field and is indexed.
     */
    private function createKeyWithAssignment(
        string $name,
        ?bool $localized = null,
        bool $indexed = true
    ): KeyConfig {
        $definition = [
            'fieldtype' => self::KEY_TYPE,
            'name' => $name,
            'title' => $name,
            'integer' => true,
        ];
        if (!$indexed) {
            $definition[self::DEFINITION_INDEXED] = false;
        }
        $keyDefinition = (string) json_encode($definition);

        $keyConfig = new KeyConfig();
        $keyConfig->setStoreId($this->storeConfig->getId());
        $keyConfig->setName($name);
        $keyConfig->setType(self::KEY_TYPE);
        $keyConfig->setDefinition($keyDefinition);
        $keyConfig->setLocalized($localized);
        $keyConfig->setEnabled(true);
        $keyConfig->save();

        $relation = new KeyGroupRelation();
        $relation->setKeyId($keyConfig->getId());
        $relation->setGroupId($this->groupConfig->getId());
        $relation->setName($name);
        $relation->setType(self::KEY_TYPE);
        $relation->setDefinition($keyDefinition);
        $relation->setEnabled(true);
        $relation->save();

        return $keyConfig;
    }

    private function deleteKeyWithAssignment(KeyConfig $keyConfig): void
    {
        $relation = new KeyGroupRelation();
        $relation->setKeyId($keyConfig->getId());
        $relation->setGroupId($this->groupConfig->getId());
        $relation->delete();
        $keyConfig->delete();
    }

    private function createClassDefinition(): ClassDefinition
    {
        $field = new ClassificationstoreField();
        $field->setName(self::FIELD_NAME);
        $field->setStoreId($this->storeConfig->getId());
        $field->setLocalized(true);

        $panel = (new Panel())->setName('MyLayout');
        $panel->addChild($field);
        $tabPanel = (new Tabpanel())->setName('Layout');
        $tabPanel->addChild($panel);
        $root = new Panel();
        $root->addChild($tabPanel);

        $classDefinition = new ClassDefinition();
        $classDefinition->setName(self::CLASS_NAME);
        $classDefinition->setLayoutDefinitions($root);
        $classDefinition->save();

        return $classDefinition;
    }

    /**
     * Other tests leave dynamically mapped fields behind in the shared indices, so the index of this
     * class is created from scratch to get the mapping the bundle itself declares.
     */
    private function recreateClassIndex(): void
    {
        /** @var IndexUpdateServiceInterface $indexUpdateService */
        $indexUpdateService = $this->tester->grabService(IndexUpdateServiceInterface::class);
        $indexUpdateService
            ->setReCreateIndex(true)
            ->updateClassDefinition($this->classDefinition);
        $this->tester->flushIndex();
    }
}
