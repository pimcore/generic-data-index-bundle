<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DataObject\FieldDefinitionAdapter;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\DataObject\FieldDefinitionAdapter\StructuredTableAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\Checkbox;
use Pimcore\Model\DataObject\ClassDefinition\Data\StructuredTable;

/**
 * @internal
 */
final class StructuredTableAdapterTest extends Unit
{
    public function testGetOpenSearchMapping(): void
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

    public function testGetOpenSearchMappingException(): void
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
}
