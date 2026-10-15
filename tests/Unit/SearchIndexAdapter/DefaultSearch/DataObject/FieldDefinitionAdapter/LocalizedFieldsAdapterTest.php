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

use Closure;
use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\CalculatedFieldsIndexMode;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\LocalizedFieldsAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedFieldsIndexModeResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedValueQueryStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Cache\RuntimeCache;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Block;
use Pimcore\Model\DataObject\ClassDefinition\Data\CalculatedValue;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyRelation;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Fieldcollection\Definition as FieldcollectionDefinition;
use Pimcore\Model\DataObject\Localizedfield;
use Pimcore\Model\DataObject\Objectbrick\Definition as ObjectbrickDefinition;

/**
 * @internal
 */
final class LocalizedFieldsAdapterTest extends Unit
{
    /** @var string[] */
    private array $runtimeCacheKeys = [];

    protected function _after(): void
    {
        foreach ($this->runtimeCacheKeys as $cacheKey) {
            RuntimeCache::getInstance()->offsetUnset($cacheKey);
        }
        $this->runtimeCacheKeys = [];
    }

    /**
     * The stored per-language values are only needed for their language / attribute
     * KEYS; the values themselves are resolved via getLocalizedValue(). Running the
     * whole Localizedfields::normalize() pass for the keys normalizes every stored
     * value just to throw the result away - per element, twice (normalize +
     * inheritance pass).
     */
    public function testNormalizeDoesNotNormalizeTheFullLocalizedfieldData(): void
    {
        $localizedFieldsDefinition = $this->createLocalizedFieldsDefinition(['title']);
        $localizedFieldsDefinition
            ->expects($this->never())
            ->method('normalize');

        $localizedField = $this->createLocalizedField(
            $localizedFieldsDefinition,
            [
                'en' => ['title' => 'Hello'],
                'de' => ['title' => 'Hallo'],
            ]
        );

        $adapter = $this->createAdapter($localizedFieldsDefinition);

        $this->assertSame(
            [
                'title' => [
                    'en' => 'Hello',
                    'de' => 'Hallo',
                ],
            ],
            $adapter->normalize($localizedField)
        );
    }

    public function testNormalizeSkipsAttributesWhoseFieldDefinitionIsGone(): void
    {
        // simulates a changed class definition: the stored data still carries
        // 'legacyField' but the definition no longer knows it
        $localizedFieldsDefinition = $this->createLocalizedFieldsDefinition(['title']);

        $localizedField = $this->createLocalizedField(
            $localizedFieldsDefinition,
            [
                'en' => [
                    'title' => 'Hello',
                    'legacyField' => 'stale',
                ],
            ]
        );

        $adapter = $this->createAdapter($localizedFieldsDefinition);

        $this->assertSame(
            [
                'title' => [
                    'en' => 'Hello',
                ],
            ],
            $adapter->normalize($localizedField)
        );
    }

    public function testNormalizeReturnsNullForEmptyLocalizedData(): void
    {
        $localizedFieldsDefinition = $this->createLocalizedFieldsDefinition(['title']);

        $localizedField = $this->createLocalizedField($localizedFieldsDefinition, []);

        $adapter = $this->createAdapter($localizedFieldsDefinition);

        $this->assertNull($adapter->normalize($localizedField));
    }

    /**
     * Localized fields inside an object brick are normalized with the brick's field definitions
     * (the class-level localized fields do not know them), at the paths of the index mapping.
     */
    public function testNormalizeLocalizedFieldsInsideObjectBrick(): void
    {
        $this->assertContainerLocalizedFieldsAreNormalized([
            'containerType' => 'objectbrick',
            'containerKey' => 'B1',
            'fieldname' => 'saleInformation',
        ]);
    }

    public function testNormalizeLocalizedFieldsInsideFieldCollection(): void
    {
        $this->assertContainerLocalizedFieldsAreNormalized([
            'containerType' => 'fieldcollection',
            'containerKey' => 'NewsText',
            'fieldname' => 'content',
        ]);
    }

    public function testNormalizeLocalizedFieldsInsideBlock(): void
    {
        $this->assertContainerLocalizedFieldsAreNormalized([
            'containerType' => 'block',
            'containerKey' => 'blockField',
            'fieldname' => 'blockField',
            'index' => 0,
        ]);
    }

    /**
     * The localized query table only holds the class-level localized fields, so a calculated value
     * inside a container is resolved live, also in query_store mode.
     */
    public function testNormalizeDoesNotUseTheQueryStoreForCalculatedValuesInsideContainers(): void
    {
        $calculatedValue = (new CalculatedValue())->setName('calculated');
        $calculatedValue->setCalculatorClass('');
        $containerDefinition = $this->createLocalizedFieldsDefinition([], [$calculatedValue]);

        $localizedField = $this->createContainerLocalizedField(
            $this->createLocalizedFieldsDefinition(['name']),
            $containerDefinition,
            ['en' => ['calculated' => null]],
            ['containerType' => 'objectbrick', 'containerKey' => 'B1', 'fieldname' => 'saleInformation'],
            // getInternalData() resets calculated values for all valid languages, which needs the system
            // configuration; the container therefore gets a definition that lists no fields
            registerEmptyContainerDefinition: true
        );

        $adapter = $this->createAdapter(
            $containerDefinition,
            mode: CalculatedFieldsIndexMode::QUERY_STORE,
            queryStore: $this->makeEmpty(CalculatedValueQueryStoreServiceInterface::class, [
                'getLocalizedValue' => Expected::never(),
            ])
        );

        $this->assertSame(['calculated' => ['en' => null]], $adapter->normalize($localizedField));
    }

    /**
     * @return array<string, array{0: array}>
     */
    public static function classLevelContextProvider(): array
    {
        return [
            'not saved' => [[]],
            'saved' => [['containerType' => 'object']],
        ];
    }

    /**
     * @dataProvider classLevelContextProvider
     */
    public function testNormalizeUsesTheQueryStoreForClassLevelCalculatedValues(array $context): void
    {
        $calculatedValue = (new CalculatedValue())->setName('calculated');
        $calculatedValue->setCalculatorClass('');
        $localizedFieldsDefinition = $this->createLocalizedFieldsDefinition([], [$calculatedValue]);

        $localizedField = new Localizedfield(['en' => ['calculated' => null]]);
        // getInternalData() resets calculated values for all valid languages, which needs the system
        // configuration; the class therefore gets a definition that lists no fields
        $localizedField->setObject($this->createDataObject(
            $this->createDefinitionWithoutFieldList($localizedFieldsDefinition)
        ));
        $localizedField->setContext($context);
        $localizedField->setLoadedAllLazyData();

        $adapter = $this->createAdapter(
            $localizedFieldsDefinition,
            mode: CalculatedFieldsIndexMode::QUERY_STORE,
            queryStore: $this->makeEmpty(CalculatedValueQueryStoreServiceInterface::class, [
                'getLocalizedValue' => Expected::once('stored value'),
            ])
        );

        $this->assertSame(['calculated' => ['en' => 'stored value']], $adapter->normalize($localizedField));
    }

    /**
     * The class level moves the attributes up (IndexMappingService); the structure below them stays
     * <attribute>.<language>, as before.
     */
    public function testIndexMappingIsMappedByAttributeAndLanguage(): void
    {
        $adapter = $this->createAdapter($this->createLocalizedFieldsDefinition(['title']));

        $this->assertSame(
            [
                'properties' => [
                    'title' => [
                        'type' => 'object',
                        'properties' => [
                            'en' => ['type' => 'keyword'],
                            'de' => ['type' => 'keyword'],
                        ],
                    ],
                ],
            ],
            $adapter->getIndexMapping()
        );
    }

    private function assertContainerLocalizedFieldsAreNormalized(array $context): void
    {
        $relation = (new ManyToManyRelation())->setName('rel');
        $containerDefinition = $this->createLocalizedFieldsDefinition(['title'], [$relation]);

        $asset290 = $this->createAsset(290);
        $asset298 = $this->createAsset(298);
        $localizedField = $this->createContainerLocalizedField(
            // the class-level localized fields have no "title" and no "rel"
            $this->createLocalizedFieldsDefinition(['name']),
            $containerDefinition,
            [
                'en' => ['title' => 'Hello', 'rel' => [$asset290, $asset298]],
                'de' => ['title' => 'Hallo', 'rel' => []],
            ],
            $context
        );

        $adapter = $this->createAdapter(
            $containerDefinition,
            normalizeValue: static fn (?Data $fieldDefinition, mixed $value) => [
                'normalizedAs' => $fieldDefinition?->getFieldtype(),
                'value' => $value,
            ]
        );

        $normalized = $adapter->normalize($localizedField);
        $this->assertSame(
            [
                'title' => [
                    'en' => ['normalizedAs' => 'input', 'value' => 'Hello'],
                    'de' => ['normalizedAs' => 'input', 'value' => 'Hallo'],
                ],
                'rel' => [
                    'en' => ['normalizedAs' => 'manyToManyRelation', 'value' => [$asset290, $asset298]],
                    'de' => ['normalizedAs' => 'manyToManyRelation', 'value' => []],
                ],
            ],
            $normalized
        );

        // every normalized value has a mapped path
        $mapping = $adapter->getIndexMapping();
        foreach ($normalized as $attribute => $values) {
            foreach (array_keys($values) as $language) {
                $this->assertArrayHasKey(
                    $language,
                    $mapping['properties'][$attribute]['properties'] ?? [],
                    sprintf('%s.%s is not mapped', $attribute, $language)
                );
            }
        }
    }

    private function createContainerLocalizedField(
        Localizedfields $classLocalizedFieldsDefinition,
        Localizedfields $containerLocalizedFieldsDefinition,
        array $items,
        array $context,
        bool $registerEmptyContainerDefinition = false
    ): Localizedfield {
        $registeredDefinition = $registerEmptyContainerDefinition
            ? $this->createDefinitionWithoutFieldList($containerLocalizedFieldsDefinition)
            : $containerLocalizedFieldsDefinition;

        $dataObject = match ($context['containerType']) {
            'block' => $this->createDataObject(
                $classLocalizedFieldsDefinition,
                [$context['containerKey'] => $this->createBlockDefinition($registeredDefinition)]
            ),
            default => $this->createDataObject($classLocalizedFieldsDefinition),
        };

        if ($context['containerType'] !== 'block') {
            $containerDefinition = $context['containerType'] === 'objectbrick'
                ? new ObjectbrickDefinition()
                : new FieldcollectionDefinition();
            $containerDefinition->setKey($context['containerKey']);
            $containerDefinition->setFieldDefinitions(['localizedfields' => $registeredDefinition]);

            // Objectbrick\Definition::getByKey() and Fieldcollection\Definition::getByKey() read the runtime
            // cache first
            $cacheKey = $context['containerType'] . '_' . $context['containerKey'];
            RuntimeCache::set($cacheKey, $containerDefinition);
            $this->runtimeCacheKeys[] = $cacheKey;
        }

        $localizedField = new Localizedfield($items);
        $localizedField->setObject($dataObject);
        $localizedField->setContext($context);
        $localizedField->setLoadedAllLazyData();

        return $localizedField;
    }

    /**
     * Resolves single field definitions like $definition but lists none.
     */
    private function createDefinitionWithoutFieldList(Localizedfields $definition): Localizedfields
    {
        $definitionWithoutFieldList = $this->getMockBuilder(Localizedfields::class)
            ->onlyMethods(['getFieldDefinition', 'getFieldDefinitions'])
            ->getMock();
        $definitionWithoutFieldList->method('getFieldDefinition')->willReturnCallback(
            static fn (string $name) => $definition->getFieldDefinition($name)
        );
        $definitionWithoutFieldList->method('getFieldDefinitions')->willReturn([]);

        return $definitionWithoutFieldList;
    }

    private function createBlockDefinition(Localizedfields $localizedFieldsDefinition): Block
    {
        $block = $this->getMockBuilder(Block::class)
            ->onlyMethods(['getFieldDefinition'])
            ->getMock();
        $block->method('getFieldDefinition')->willReturnCallback(
            static fn (string $name) => $name === 'localizedfields' ? $localizedFieldsDefinition : null
        );

        return $block;
    }

    /**
     * @param string[] $fieldNames
     * @param Data[] $additionalDefinitions
     */
    private function createLocalizedFieldsDefinition(
        array $fieldNames,
        array $additionalDefinitions = []
    ): Localizedfields&MockObject {
        $childDefinitions = [];
        foreach ($fieldNames as $fieldName) {
            $childDefinitions[$fieldName] = (new Input())->setName($fieldName);
        }
        foreach ($additionalDefinitions as $additionalDefinition) {
            $childDefinitions[$additionalDefinition->getName()] = $additionalDefinition;
        }

        $definition = $this->getMockBuilder(Localizedfields::class)
            ->onlyMethods(['normalize', 'getFieldDefinition', 'getFieldDefinitions'])
            ->getMock();

        $definition
            ->method('getFieldDefinition')
            ->willReturnCallback(
                static fn (string $name) => $childDefinitions[$name] ?? null
            );
        $definition
            ->method('getFieldDefinitions')
            ->willReturn($childDefinitions);

        return $definition;
    }

    private function createLocalizedField(Localizedfields $definition, array $items): Localizedfield
    {
        $localizedField = new Localizedfield($items);
        $localizedField->setObject($this->createDataObject($definition));

        return $localizedField;
    }

    private function createAsset(int $id): Image
    {
        $asset = new Image();
        $asset->setId($id);

        return $asset;
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
        $classDefinition->setAllowInherit(false);

        return $this->makeEmpty(Concrete::class, [
            'getClass' => $classDefinition,
        ]);
    }

    private function createAdapter(
        Localizedfields $definition,
        ?Closure $normalizeValue = null,
        CalculatedFieldsIndexMode $mode = CalculatedFieldsIndexMode::LIVE,
        ?CalculatedValueQueryStoreServiceInterface $queryStore = null
    ): LocalizedFieldsAdapter {
        $adapter = new LocalizedFieldsAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class, [
                'normalizeValue' => $normalizeValue ?? static fn ($fieldDefinition, $value) => $value,
                'getFieldDefinitionAdapter' => fn (Data $fieldDefinition) => $this->makeEmpty(
                    AdapterInterface::class,
                    [
                        'getIndexAttributeName' => $fieldDefinition->getName(),
                        'getIndexMapping' => ['type' => 'keyword'],
                    ]
                ),
            ]),
        );
        $adapter->setLanguageService($this->makeEmpty(LanguageServiceInterface::class, [
            'getValidLanguages' => ['en', 'de'],
        ]));
        $adapter->setCalculatedFieldsIndexModeResolver($this->makeEmpty(
            CalculatedFieldsIndexModeResolverInterface::class,
            ['getMode' => $mode]
        ));
        $adapter->setCalculatedValueQueryStoreService(
            $queryStore ?? $this->makeEmpty(CalculatedValueQueryStoreServiceInterface::class)
        );
        $adapter->setFieldDefinition($definition);

        return $adapter;
    }
}
