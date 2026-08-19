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

use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\CalculatedFieldsIndexMode;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\LocalizedFieldsAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedFieldsIndexModeResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedValueQueryStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\LanguageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Localizedfield;

/**
 * @internal
 */
final class LocalizedFieldsAdapterTest extends Unit
{
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
     * @param string[] $fieldNames
     */
    private function createLocalizedFieldsDefinition(array $fieldNames): Localizedfields&MockObject
    {
        $childDefinitions = [];
        foreach ($fieldNames as $fieldName) {
            $childDefinitions[$fieldName] = (new Input())->setName($fieldName);
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
        $classDefinition = new ClassDefinition();
        $classDefinition->setFieldDefinitions(['localizedfields' => $definition]);
        $classDefinition->setAllowInherit(false);

        $dataObject = $this->makeEmpty(Concrete::class, [
            'getClass' => $classDefinition,
        ]);

        $localizedField = new Localizedfield($items);
        $localizedField->setObject($dataObject);

        return $localizedField;
    }

    private function createAdapter(Localizedfields $definition): LocalizedFieldsAdapter
    {
        $adapter = new LocalizedFieldsAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class, [
                'normalizeValue' => static fn ($fieldDefinition, $value) => $value,
            ]),
        );
        $adapter->setLanguageService($this->makeEmpty(LanguageServiceInterface::class));
        $adapter->setCalculatedFieldsIndexModeResolver($this->makeEmpty(
            CalculatedFieldsIndexModeResolverInterface::class,
            ['getMode' => CalculatedFieldsIndexMode::LIVE]
        ));
        $adapter->setCalculatedValueQueryStoreService(
            $this->makeEmpty(CalculatedValueQueryStoreServiceInterface::class)
        );
        $adapter->setFieldDefinition($definition);

        return $adapter;
    }
}
