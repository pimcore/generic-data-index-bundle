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
use PHPUnit\Framework\MockObject\MockObject;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\CalculatedFieldsIndexMode;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\AbstractAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\BlockAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\FieldCollectionAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\LocalizedFieldsAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\ObjectBrickAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedFieldsIndexModeResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedValueQueryStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\FieldCollection\DefinitionResolverInterface as FieldCollectionDefinitionResolverInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\Objectbrick\DefinitionResolverInterface as ObjectBrickDefinitionResolverInterface;
use Pimcore\Cache\RuntimeCache;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Block;
use Pimcore\Model\DataObject\ClassDefinition\Data\CalculatedValue;
use Pimcore\Model\DataObject\ClassDefinition\Data\Fieldcollections;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\ClassDefinition\Data\Numeric;
use Pimcore\Model\DataObject\ClassDefinition\Data\Objectbricks;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Data\BlockElement;
use Pimcore\Model\DataObject\Fieldcollection;
use Pimcore\Model\DataObject\Fieldcollection\Data\AbstractData as FieldCollectionItem;
use Pimcore\Model\DataObject\Fieldcollection\Definition as FieldCollectionDefinition;
use Pimcore\Model\DataObject\Localizedfield;
use Pimcore\Model\DataObject\Objectbrick;
use Pimcore\Model\DataObject\Objectbrick\Data\AbstractData as ObjectBrickItem;
use Pimcore\Model\DataObject\Objectbrick\Definition as ObjectBrickDefinition;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Localized fields inside object bricks, field collections and blocks are indexed like the class-level
 * ones: <container path>.<attribute>.<language>, without a "localizedfields" level. Studio filters and
 * sorts brick columns by <field>.<brick>.<attribute>.<language>. normalize() keeps the "localizedfields" key
 * for its other consumers (e.g. the embedding text of backend-power-tools); flattenLocalizedFields() builds the
 * document structure.
 *
 * @internal
 */
final class ContainerLocalizedFieldsTest extends Unit
{
    private const LANGUAGES = ['en', 'de'];

    private LocalizedFieldsAdapter $localizedFieldsAdapter;

    private FieldDefinitionService $fieldDefinitionService;

    private ?BlockAdapter $blockAdapter = null;

    /** @var string[] */
    private array $runtimeCacheKeys = [];

    protected function _after(): void
    {
        foreach ($this->runtimeCacheKeys as $cacheKey) {
            RuntimeCache::getInstance()->offsetUnset($cacheKey);
        }
        $this->runtimeCacheKeys = [];
    }

    protected function _before(): void
    {
        $this->setUpServices(CalculatedFieldsIndexMode::LIVE, $this->makeEmpty(
            CalculatedValueQueryStoreServiceInterface::class
        ));
    }

    public function testObjectBrickMappingHasTheLocalizedAttributesNextToTheOtherFields(): void
    {
        $adapter = $this->createObjectBrickAdapter($this->createContainerLocalizedFieldsDefinition());

        $this->assertSameMapping(
            ['properties' => ['B1' => ['properties' => $this->expectedContainerMapping()]]],
            $adapter->getIndexMapping()
        );
    }

    public function testObjectBrickValuesAreIndexedAtTheMappedPaths(): void
    {
        $adapter = $this->createObjectBrickAdapter($this->createContainerLocalizedFieldsDefinition());
        $item = $this->createObjectBrickItem('B1', 'SKU-1', $this->createLocalizedField(
            ['containerType' => 'objectbrick', 'containerKey' => 'B1', 'fieldname' => 'bricks']
        ));
        $brick = $this->makeEmpty(Objectbrick::class, ['getObjectVars' => ['b1' => $item]]);

        $normalized = $adapter->normalize($brick);
        $this->assertSame(['B1' => $this->expectedNormalizedValues()], $this->sortedValues($normalized));

        $document = $adapter->flattenLocalizedFields($normalized);
        $this->assertSame(['B1' => $this->expectedContainerValues()], $this->sortedValues($document));
        $this->assertEveryValueIsMapped($document['B1'], $adapter->getIndexMapping()['properties']['B1']);
    }

    public function testFieldCollectionMappingHasTheLocalizedAttributesNextToTheOtherFields(): void
    {
        $adapter = $this->createFieldCollectionAdapter($this->createContainerLocalizedFieldsDefinition());

        $this->assertSameMapping(
            [
                'type' => AttributeType::NESTED,
                'properties' => [
                    'FC1' => ['type' => AttributeType::NESTED, 'properties' => $this->expectedContainerMapping()],
                ],
            ],
            $adapter->getIndexMapping()
        );
    }

    public function testFieldCollectionValuesAreIndexedAtTheMappedPaths(): void
    {
        $adapter = $this->createFieldCollectionAdapter($this->createContainerLocalizedFieldsDefinition());
        $item = $this->createFieldCollectionItem('FC1', [
            'sku' => 'SKU-1',
            'localizedfields' => $this->createLocalizedField(
                [
                    'containerType' => 'fieldcollection',
                    'containerKey' => 'FC1',
                    'fieldname' => 'collection',
                    'index' => 0,
                ]
            ),
        ]);
        $fieldCollection = $this->makeEmpty(Fieldcollection::class, ['getItems' => [$item]]);

        $normalized = $adapter->normalize($fieldCollection);
        $this->assertSame([['FC1' => $this->expectedNormalizedValues()]], $this->sortedValues($normalized));

        $document = $adapter->flattenLocalizedFields($normalized);
        $this->assertSame([['FC1' => $this->expectedContainerValues()]], $this->sortedValues($document));
        $this->assertEveryValueIsMapped(
            $document[0]['FC1'],
            $adapter->getIndexMapping()['properties']['FC1']
        );
    }

    public function testBlockMappingHasTheLocalizedAttributesNextToTheOtherFields(): void
    {
        $adapter = $this->createBlockAdapter($this->createContainerLocalizedFieldsDefinition());

        $this->assertSameMapping(
            ['type' => 'nested', 'properties' => $this->expectedContainerMapping()],
            $adapter->getIndexMapping()
        );
    }

    public function testBlockValuesAreIndexedAtTheMappedPaths(): void
    {
        $adapter = $this->createBlockAdapter($this->createContainerLocalizedFieldsDefinition());
        $blockData = [[
            'sku' => new BlockElement('sku', 'input', 'SKU-1'),
            'localizedfields' => new BlockElement('localizedfields', 'localizedfields', $this->createLocalizedField(
                ['containerType' => 'block', 'containerKey' => 'blockField', 'fieldname' => 'blockField', 'index' => 0],
                $adapter->getFieldDefinition()
            )),
        ]];

        $normalized = $adapter->normalize($blockData);
        $this->assertSame([$this->expectedNormalizedValues()], $this->sortedValues($normalized));

        $document = $adapter->flattenLocalizedFields($normalized);
        $this->assertSame([$this->expectedContainerValues()], $this->sortedValues($document));
        $this->assertEveryValueIsMapped($document[0], $adapter->getIndexMapping());
    }

    /**
     * A block inside a field collection: its localized fields are flattened as well.
     * The block is resolved here through the given definition; core resolves a block inside a brick or field
     * collection only through the class definition, so this tests the flattening, not that nesting end to end.
     */
    public function testLocalizedFieldsOfANestedBlockAreFlattened(): void
    {
        $block = $this->createBlockDefinition('blk', $this->createContainerFields(
            $this->createContainerLocalizedFieldsDefinition()
        ));
        $adapter = $this->createFieldCollectionAdapter(null, [(new Input())->setName('sku'), $block]);
        $item = $this->createFieldCollectionItem('FC1', [
            'sku' => 'SKU-1',
            'blk' => [[
                'sku' => new BlockElement('sku', 'input', 'SKU-2'),
                'localizedfields' => new BlockElement('localizedfields', 'localizedfields', $this->createLocalizedField(
                    ['containerType' => 'block', 'containerKey' => 'blk', 'fieldname' => 'blk', 'index' => 0],
                    $block
                )),
            ]],
        ]);

        $document = $adapter->flattenLocalizedFields(
            $adapter->normalize($this->makeEmpty(Fieldcollection::class, ['getItems' => [$item]]))
        );

        $expectedBlockItem = $this->expectedContainerValues();
        $expectedBlockItem['sku']['value'] = 'SKU-2';
        $this->assertSame(
            [['FC1' => ['blk' => [$expectedBlockItem], 'sku' => ['as' => 'input', 'value' => 'SKU-1']]]],
            $this->sortedValues($document)
        );
        $this->assertEveryValueIsMapped(
            $document[0]['FC1']['blk'][0],
            $adapter->getIndexMapping()['properties']['FC1']['properties']['blk']
        );
    }

    /**
     * A block inside a block: the inner block's localized fields are flattened.
     * Same limit as above: a fixture structure, not proven against core's resolution.
     */
    public function testLocalizedFieldsOfABlockInsideABlockAreFlattened(): void
    {
        $inner = $this->createBlockDefinition('inner', $this->createContainerFields(
            $this->createContainerLocalizedFieldsDefinition()
        ));
        $outer = $this->createBlockDefinition('outer', [(new Input())->setName('sku'), $inner]);
        $blockData = [[
            'sku' => new BlockElement('sku', 'input', 'SKU-1'),
            'inner' => new BlockElement('inner', 'block', [[
                'sku' => new BlockElement('sku', 'input', 'SKU-2'),
                'localizedfields' => new BlockElement('localizedfields', 'localizedfields', $this->createLocalizedField(
                    ['containerType' => 'block', 'containerKey' => 'inner', 'fieldname' => 'inner', 'index' => 0],
                    $inner
                )),
            ]]),
        ]];

        // the adapter is fetched before each call, as FieldDefinitionService and DataObjectNormalizer do
        $mapping = $this->fieldDefinitionService->getFieldDefinitionAdapter($outer)->getIndexMapping();
        $normalized = $this->fieldDefinitionService->normalizeValue($outer, $blockData);
        $document = $this->fieldDefinitionService->getFieldDefinitionAdapter($outer)
            ->flattenLocalizedFields($normalized);

        $expectedInnerItem = $this->expectedContainerValues();
        $expectedInnerItem['sku']['value'] = 'SKU-2';
        $this->assertSame(
            [['inner' => [$expectedInnerItem], 'sku' => ['as' => 'input', 'value' => 'SKU-1']]],
            $this->sortedValues($document)
        );
        $this->assertEveryValueIsMapped($document[0]['inner'][0], $mapping['properties']['inner']);
    }

    /**
     * A block inside localized fields that contains another block: every language gets the same mapping.
     */
    public function testEveryLanguageGetsTheSameMappingForANestedContainer(): void
    {
        $inner = $this->createBlockDefinition('inner', [(new Input())->setName('title')]);
        $outer = $this->createBlockDefinition('outer', [(new Input())->setName('sku'), $inner]);
        $this->localizedFieldsAdapter->setFieldDefinition($this->createLocalizedFieldsDefinition(['outer' => $outer]));

        $mapping = $this->localizedFieldsAdapter->getIndexMapping()['properties']['outer']['properties'];

        $this->assertSame(['sku', 'inner'], array_keys($mapping['en']['properties']));
        $this->assertSame($mapping['en'], $mapping['de']);
    }

    public function testContainersWithoutLocalizedFieldsKeepTheirMapping(): void
    {
        $sku = (new Input())->setName('sku');

        $brickAdapter = $this->createObjectBrickAdapter(null, [$sku]);
        $this->assertSame(
            ['properties' => ['B1' => ['properties' => ['sku' => ['type' => 'input']]]]],
            $brickAdapter->getIndexMapping()
        );

        $fieldCollectionAdapter = $this->createFieldCollectionAdapter(null, [$sku]);
        $this->assertSame(
            [
                'type' => AttributeType::NESTED,
                'properties' => [
                    'FC1' => ['type' => AttributeType::NESTED, 'properties' => ['sku' => ['type' => 'input']]],
                ],
            ],
            $fieldCollectionAdapter->getIndexMapping()
        );

        $blockAdapter = $this->createBlockAdapter(null, [$sku]);
        $this->assertSame(
            ['type' => 'nested', 'properties' => ['sku' => ['type' => 'input']]],
            $blockAdapter->getIndexMapping()
        );
    }

    /**
     * The localized query table only holds the class-level localized fields. A calculated value inside a
     * container is calculated live in query_store mode, also when its localized fields carry no container
     * context (e.g. a Localizedfield created by custom code).
     */
    public function testCalculatedValuesInsideAnObjectBrickAreNotReadFromTheQueryStore(): void
    {
        $this->setUpServices(
            CalculatedFieldsIndexMode::QUERY_STORE,
            $this->makeEmpty(CalculatedValueQueryStoreServiceInterface::class, [
                'getLocalizedValue' => Expected::never(),
            ])
        );
        $calculated = (new CalculatedValue())->setName('calculated');
        $calculated->setCalculatorClass('');
        $definition = $this->createLocalizedFieldsDefinition(['calculated' => $calculated]);
        $adapter = $this->createObjectBrickAdapter($definition);

        // No context, so Localizedfield falls back to the class-level localized fields, which here have a
        // calculated field of the same name. They list no fields, so getInternalData() does not reset
        // calculated values (that needs the system configuration).
        $localizedField = new Localizedfield(['en' => ['calculated' => null]]);
        $localizedField->setObject($this->createDataObject(
            $this->createLocalizedFieldsDefinition(['calculated' => $calculated], listsFields: false)
        ));
        $localizedField->setLoadedAllLazyData();
        $item = $this->createObjectBrickItem('B1', 'SKU-1', $localizedField);

        $normalized = $adapter->normalize(
            $this->makeEmpty(Objectbrick::class, ['getObjectVars' => ['b1' => $item]])
        );

        $this->assertSame(
            ['en' => ['as' => 'calculatedValue', 'value' => null]],
            $normalized['B1']['localizedfields']['calculated']
        );
    }

    private function setUpServices(
        CalculatedFieldsIndexMode $mode,
        CalculatedValueQueryStoreServiceInterface $queryStore
    ): void {
        $this->blockAdapter = null;
        $searchIndexConfigService = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $typeAdapter = fn () => $this->createTypeAdapter($searchIndexConfigService, $this->fieldDefinitionService);
        $this->fieldDefinitionService = new FieldDefinitionService(new ServiceLocator([
            'localizedfields' => fn () => $this->localizedFieldsAdapter,
            // one cached instance (a test double; the real adapters are shared: false)
            'block' => fn () => $this->blockAdapter ??= new BlockAdapter(
                $searchIndexConfigService,
                $this->fieldDefinitionService
            ),
            'input' => $typeAdapter,
            'numeric' => $typeAdapter,
            'calculatedValue' => $typeAdapter,
        ]));

        $this->localizedFieldsAdapter = new LocalizedFieldsAdapter(
            $searchIndexConfigService,
            $this->fieldDefinitionService
        );
        $this->localizedFieldsAdapter->setLanguageService($this->makeEmpty(LanguageServiceInterface::class, [
            'getValidLanguages' => self::LANGUAGES,
        ]));
        $this->localizedFieldsAdapter->setCalculatedFieldsIndexModeResolver($this->makeEmpty(
            CalculatedFieldsIndexModeResolverInterface::class,
            ['getMode' => $mode]
        ));
        $this->localizedFieldsAdapter->setCalculatedValueQueryStoreService($queryStore);
    }

    /**
     * Maps every field to its field type and normalizes a value to its field type and the raw value, so the
     * tests see which definition a value went through.
     */
    private function createTypeAdapter(
        SearchIndexConfigServiceInterface $searchIndexConfigService,
        FieldDefinitionService $fieldDefinitionService
    ): AdapterInterface {
        return new class($searchIndexConfigService, $fieldDefinitionService) extends AbstractAdapter {
            public function getIndexMapping(): array
            {
                return ['type' => $this->getFieldDefinition()->getFieldtype()];
            }

            public function normalize(mixed $value): mixed
            {
                return ['as' => $this->getFieldDefinition()->getFieldtype(), 'value' => $value];
            }
        };
    }

    private function createObjectBrickAdapter(
        ?Localizedfields $localizedFieldsDefinition,
        ?array $fieldDefinitions = null
    ): ObjectBrickAdapter {
        $brickDefinition = $this->createContainerDefinition(
            ObjectBrickDefinition::class,
            $fieldDefinitions ?? $this->createContainerFields($localizedFieldsDefinition)
        );
        // Objectbrick\Definition::getByKey() (used by Localizedfield for the brick context) reads the runtime cache
        $this->registerInRuntimeCache('objectbrick_B1', $brickDefinition);

        $adapter = new ObjectBrickAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->fieldDefinitionService
        );
        $adapter->setObjectBrickDefinition($this->makeEmpty(ObjectBrickDefinitionResolverInterface::class, [
            'getByKey' => $brickDefinition,
        ]));
        // setAllowedTypes() would load the brick definitions from the file system
        $objectBricks = $this->makeEmpty(Objectbricks::class, ['getAllowedTypes' => ['B1'], 'getName' => 'bricks']);
        $adapter->setFieldDefinition($objectBricks);

        return $adapter;
    }

    private function createFieldCollectionAdapter(
        ?Localizedfields $localizedFieldsDefinition,
        ?array $fieldDefinitions = null
    ): FieldCollectionAdapter {
        $fieldCollectionDefinition = $this->createContainerDefinition(
            FieldCollectionDefinition::class,
            $fieldDefinitions ?? $this->createContainerFields($localizedFieldsDefinition)
        );
        $this->registerInRuntimeCache('fieldcollection_FC1', $fieldCollectionDefinition);

        $adapter = new FieldCollectionAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->fieldDefinitionService
        );
        $adapter->setFieldCollectionDefinition($this->makeEmpty(FieldCollectionDefinitionResolverInterface::class, [
            'getByKey' => $fieldCollectionDefinition,
        ]));
        $fieldCollections = $this->makeEmpty(Fieldcollections::class, [
            'getAllowedTypes' => ['FC1'],
            'getName' => 'collection',
        ]);
        $adapter->setFieldDefinition($fieldCollections);

        return $adapter;
    }

    private function createBlockAdapter(
        ?Localizedfields $localizedFieldsDefinition,
        ?array $fieldDefinitions = null
    ): BlockAdapter {
        $block = $this->createBlockDefinition(
            'blockField',
            $fieldDefinitions ?? $this->createContainerFields($localizedFieldsDefinition)
        );

        $adapter = new BlockAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->fieldDefinitionService
        );
        $adapter->setFieldDefinition($block);

        return $adapter;
    }

    /**
     * @param Data[] $fieldDefinitions
     */
    private function createBlockDefinition(string $name, array $fieldDefinitions): Block
    {
        $fields = [];
        foreach ($fieldDefinitions as $fieldDefinition) {
            $fields[$fieldDefinition->getName()] = $fieldDefinition;
        }
        $block = $this->getMockBuilder(Block::class)
            ->onlyMethods(['getFieldDefinitions', 'getFieldDefinition'])
            ->getMock();
        $block->method('getFieldDefinitions')->willReturn($fields);
        $block->method('getFieldDefinition')->willReturnCallback(static fn (string $field) => $fields[$field] ?? null);
        $block->setName($name);

        return $block;
    }

    /**
     * @return Data[]
     */
    private function createContainerFields(?Localizedfields $localizedFieldsDefinition): array
    {
        $fields = [(new Input())->setName('sku')];
        if ($localizedFieldsDefinition) {
            $fields[] = $localizedFieldsDefinition;
        }

        return $fields;
    }

    /**
     * title (input) and rating (numeric). The class-level localized fields also have a "title", as a
     * numeric field: a lookup without the container's definition would take that one.
     */
    private function createContainerLocalizedFieldsDefinition(): Localizedfields
    {
        return $this->createLocalizedFieldsDefinition([
            'title' => (new Input())->setName('title'),
            'rating' => (new Numeric())->setName('rating'),
        ]);
    }

    /**
     * With the context Pimcore sets when it loads a container; it resolves the container's definition.
     */
    private function createLocalizedField(array $context, ?Block $block = null): Localizedfield
    {
        $localizedField = new Localizedfield([
            'en' => ['title' => 'Hello', 'rating' => 4],
            'de' => ['title' => 'Hallo', 'rating' => null],
        ]);
        $localizedField->setObject($this->createDataObject(
            $this->createLocalizedFieldsDefinition(['title' => (new Numeric())->setName('title')]),
            $block ? [$block->getName() => $block] : []
        ));
        $localizedField->setContext($context);
        $localizedField->setLoadedAllLazyData();

        return $localizedField;
    }

    /**
     * @template T of FieldCollectionDefinition
     *
     * @param class-string<T> $class
     * @param Data[] $fields
     *
     * @return T
     */
    private function createContainerDefinition(string $class, array $fields): FieldCollectionDefinition
    {
        $byName = [];
        foreach ($fields as $field) {
            $byName[$field->getName()] = $field;
        }

        return $this->makeEmpty($class, [
            'getFieldDefinitions' => $byName,
            'getFieldDefinition' => static fn (string $name) => $byName[$name] ?? null,
        ]);
    }

    private function registerInRuntimeCache(string $cacheKey, mixed $value): void
    {
        RuntimeCache::set($cacheKey, $value);
        $this->runtimeCacheKeys[] = $cacheKey;
    }

    private function expectedContainerMapping(): array
    {
        return [
            'rating' => [
                'type' => 'object',
                'properties' => ['en' => ['type' => 'numeric'], 'de' => ['type' => 'numeric']],
            ],
            'sku' => ['type' => 'input'],
            'title' => [
                'type' => 'object',
                'properties' => ['en' => ['type' => 'input'], 'de' => ['type' => 'input']],
            ],
        ];
    }

    private function expectedContainerValues(): array
    {
        return [
            'rating' => [
                'de' => ['as' => 'numeric', 'value' => null],
                'en' => ['as' => 'numeric', 'value' => 4],
            ],
            'sku' => ['as' => 'input', 'value' => 'SKU-1'],
            'title' => [
                'de' => ['as' => 'input', 'value' => 'Hallo'],
                'en' => ['as' => 'input', 'value' => 'Hello'],
            ],
        ];
    }

    /**
     * What normalize() returns: the localized fields stay below "localizedfields".
     */
    private function expectedNormalizedValues(): array
    {
        $values = $this->expectedContainerValues();

        return [
            'localizedfields' => ['rating' => $values['rating'], 'title' => $values['title']],
            'sku' => $values['sku'],
        ];
    }

    /**
     * Every leaf of the normalized values has a path in the mapping.
     */
    private function assertEveryValueIsMapped(array $values, array $mapping): void
    {
        foreach ($values as $field => $value) {
            $this->assertArrayHasKey($field, $mapping['properties'], sprintf('%s is not mapped', $field));
            if (isset($mapping['properties'][$field]['properties'])) {
                foreach (array_keys($value) as $language) {
                    $this->assertArrayHasKey(
                        $language,
                        $mapping['properties'][$field]['properties'],
                        sprintf('%s.%s is not mapped', $field, $language)
                    );
                }
            }
        }
    }

    /**
     * Key order does not matter for a mapping.
     */
    private function assertSameMapping(array $expected, array $actual): void
    {
        $this->assertSame($this->sortRecursive($expected), $this->sortRecursive($actual));
    }

    private function sortedValues(?array $values): ?array
    {
        return $values === null ? null : $this->sortRecursive($values);
    }

    private function sortRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sortRecursive($value);
            }
        }
        if (!array_is_list($data)) {
            ksort($data);
        }

        return $data;
    }

    /**
     * @param array<string, Data> $childDefinitions
     */
    private function createLocalizedFieldsDefinition(
        array $childDefinitions,
        bool $listsFields = true
    ): Localizedfields&MockObject {
        $definition = $this->getMockBuilder(Localizedfields::class)
            ->onlyMethods(['getFieldDefinition', 'getFieldDefinitions'])
            ->getMock();
        $definition->method('getFieldDefinition')->willReturnCallback(
            static fn (string $name) => $childDefinitions[$name] ?? null
        );
        $definition->method('getFieldDefinitions')->willReturn($listsFields ? $childDefinitions : []);
        $definition->setName('localizedfields');

        return $definition;
    }

    /**
     * @param Data[] $additionalFieldDefinitions
     */
    private function createDataObject(
        Localizedfields $classLocalizedFieldsDefinition,
        array $additionalFieldDefinitions = []
    ): Concrete {
        $classDefinition = new ClassDefinition();
        $classDefinition->setFieldDefinitions(
            ['localizedfields' => $classLocalizedFieldsDefinition] + $additionalFieldDefinitions
        );

        return $this->makeEmpty(Concrete::class, ['getClass' => $classDefinition]);
    }

    private function createObjectBrickItem(string $type, string $sku, Localizedfield $localizedField): ObjectBrickItem
    {
        $item = new class($this->makeEmpty(Concrete::class)) extends ObjectBrickItem {
            public ?string $sku = null;

            public ?Localizedfield $localizedfields = null;

            public function setItemType(string $type): void
            {
                $this->type = $type;
            }

            public function getSku(): ?string
            {
                return $this->sku;
            }

            public function getLocalizedfields(): ?Localizedfield
            {
                return $this->localizedfields;
            }
        };
        $item->setItemType($type);
        $item->sku = $sku;
        $item->localizedfields = $localizedField;

        return $item;
    }

    /**
     * @param array<string, mixed> $values field name => value, returned by the item's getters
     */
    private function createFieldCollectionItem(string $type, array $values): FieldCollectionItem
    {
        $item = new class() extends FieldCollectionItem {
            /** @var array<string, mixed> */
            public array $values = [];

            public function setItemType(string $type): void
            {
                $this->type = $type;
            }

            public function __call(mixed $method, mixed $arguments): mixed
            {
                return $this->values[lcfirst(substr((string) $method, 3))] ?? null;
            }
        };
        $item->setItemType($type);
        $item->values = $values;

        return $item;
    }
}
