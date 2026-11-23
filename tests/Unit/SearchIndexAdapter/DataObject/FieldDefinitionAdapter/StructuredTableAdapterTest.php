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
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\StructuredTableAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data\Checkbox;
use Pimcore\Model\DataObject\ClassDefinition\Data\StructuredTable;

/**
 * @internal
 */
final class StructuredTableAdapterTest extends Unit
{
    public function testGetSearchIndexMapping(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $adapter = new StructuredTableAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );
        $structuredTable = new StructuredTable();
        $structuredTable->setRows([
            [
                'key' => 'row1',
                'position' => 1,
            ],
            [
                'key' => 'row2',
                'position' => 2,
            ],
        ]);
        $structuredTable->setCols([
            [
                'key' => 'col1',
                'type' => 'number',
                'position' => 1,
            ],
            [
                'key' => 'col2',
                'type' => 'text',
                'position' => 2,
            ],
            [
                'key' => 'col3',
                'type' => 'bool',
                'position' => 3,
            ],
        ]);

        $adapter->setFieldDefinition($structuredTable);

        $this->assertSame([
            'properties' => [
                'row1' => [
                    'type' => AttributeType::NESTED->value,
                    'properties' => [
                        'col1' => [
                            'type' => AttributeType::FLOAT->value,
                        ],
                        'col2' => [
                            'type' => AttributeType::KEYWORD->value,
                        ],
                        'col3' => [
                            'type' => AttributeType::INTEGER->value,
                        ],
                    ],
                ],
                'row2' => [
                    'type' => AttributeType::NESTED->value,
                    'properties' => [
                        'col1' => [
                            'type' => AttributeType::FLOAT->value,
                        ],
                        'col2' => [
                            'type' => AttributeType::KEYWORD->value,
                        ],
                        'col3' => [
                            'type' => AttributeType::INTEGER->value,
                        ],
                    ],
                ],
            ],
        ], $adapter->getIndexMapping());
    }

    public function testNormalizeStructuredTable(): void
    {
        $adapter = $this->createAdapter();

        $value = new DataObject\Data\StructuredTable([
            'row1' => [
                'col1' => 1.5,
                'col2' => 'text',
            ],
        ]);

        $this->assertSame([
            'row1' => [
                'col1' => 1.5,
                'col2' => 'text',
            ],
        ], $adapter->normalize($value));
    }

    /**
     * A classification store key of type structuredTable that holds no value hands the adapter null.
     * StructuredTable::normalize() answers null for anything that is not a StructuredTable instance,
     * which used to be iterated directly.
     */
    public function testNormalizeEmptyValue(): void
    {
        $adapter = $this->createAdapter();

        $this->assertNull($adapter->normalize(null));
    }

    public function testNormalizeRowThatIsNotATable(): void
    {
        $adapter = $this->createAdapter();

        $value = new DataObject\Data\StructuredTable();
        $value->setData(['row1' => 'not-a-column-list']);

        $this->assertSame([], $adapter->normalize($value));
    }

    public function testGetSearchIndexMappingException(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $fieldDefinitionServiceInterfaceMock = $this->makeEmpty(FieldDefinitionServiceInterface::class);
        $adapter = new StructuredTableAdapter(
            $searchIndexConfigServiceInterfaceMock,
            $fieldDefinitionServiceInterfaceMock
        );
        $table = new Checkbox();
        $adapter->setFieldDefinition($table);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'FieldDefinition must be of type StructuredTable'
        );
        $adapter->getIndexMapping();
    }

    private function createAdapter(): StructuredTableAdapter
    {
        $adapter = new StructuredTableAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class)
        );
        $adapter->setFieldDefinition(new StructuredTable());

        return $adapter;
    }
}
