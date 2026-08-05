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

use Carbon\Carbon;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\CalculatedValueAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\CalculatedValue;

/**
 * @internal
 */
final class CalculatedValueAdapterTest extends Unit
{
    public function testGetSearchIndexMappingForBooleanElementType(): void
    {
        $adapter = $this->createAdapter('boolean');

        $this->assertSame([
            'type' => 'boolean',
        ], $adapter->getIndexMapping());
    }

    public function testGetSearchIndexMappingForNumericElementType(): void
    {
        $adapter = $this->createAdapter('numeric');

        $this->assertSame([
            'type' => 'float',
        ], $adapter->getIndexMapping());
    }

    public function testGetSearchIndexMappingForDateElementType(): void
    {
        $adapter = $this->createAdapter('date');

        $this->assertSame([
            'type' => 'date',
            'format' => 'strict_date_time_no_millis',
        ], $adapter->getIndexMapping());
    }

    public function testGetSearchIndexMappingFallsBackToTextKeyword(): void
    {
        $textKeywordMapping = [
            'type' => 'text',
            'fields' => [
                'keyword' => [
                    'type' => 'keyword',
                    'ignore_above' => 1024,
                ],
            ],
        ];

        foreach (['input', 'textarea', 'html'] as $elementType) {
            $adapter = $this->createAdapter($elementType, $textKeywordMapping);

            $this->assertSame($textKeywordMapping, $adapter->getIndexMapping());
        }
    }

    /**
     * Calculators are free to return loosely typed values (e.g. '0'/'1' from
     * expression results), which a boolean index mapping would reject.
     */
    public function testNormalizeCastsBooleanElementTypeValues(): void
    {
        $adapter = $this->createAdapter('boolean');

        $this->assertTrue($adapter->normalize(true));
        $this->assertFalse($adapter->normalize(false));
        $this->assertTrue($adapter->normalize('1'));
        $this->assertFalse($adapter->normalize('0'));
        $this->assertTrue($adapter->normalize(1));
        $this->assertFalse($adapter->normalize(0));
        $this->assertNull($adapter->normalize(null));
    }

    public function testNormalizeCastsNumericElementTypeValues(): void
    {
        $adapter = $this->createAdapter('numeric');

        $this->assertSame(1.5, $adapter->normalize(1.5));
        $this->assertSame(3.0, $adapter->normalize(3));
        $this->assertSame(2.25, $adapter->normalize('2.25'));
        $this->assertNull($adapter->normalize('not a number'));
        $this->assertNull($adapter->normalize(null));
    }

    public function testNormalizeFormatsDateElementTypeValues(): void
    {
        $adapter = $this->createAdapter('date');

        $this->assertSame(
            '2024-06-15T10:30:00+00:00',
            $adapter->normalize(Carbon::create(2024, 6, 15, 10, 30, 0, 'UTC'))
        );
        $this->assertNull($adapter->normalize('2024-06-15'));
        $this->assertNull($adapter->normalize(null));
    }

    public function testNormalizeKeepsTextElementTypeBehavior(): void
    {
        $adapter = $this->createAdapter('html');

        $this->assertSame(
            '<img  alt="test">',
            $adapter->normalize('<img src="data:image/png;base64,iVBORw0KGgo=" alt="test">')
        );
        $this->assertSame('plain text', $adapter->normalize('plain text'));
    }

    private function createAdapter(string $elementType, array $textKeywordMapping = []): CalculatedValueAdapter
    {
        $indexMappingServiceMock = $this->makeEmpty(IndexMappingServiceInterface::class, [
            'getMappingForTextKeyword' => $textKeywordMapping,
        ]);

        $adapter = new CalculatedValueAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class),
            $indexMappingServiceMock
        );

        $fieldDefinition = new CalculatedValue();
        $fieldDefinition->setElementType($elementType);
        $adapter->setFieldDefinition($fieldDefinition);

        return $adapter;
    }
}
