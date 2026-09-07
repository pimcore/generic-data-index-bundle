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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\TableAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\Table;

/**
 * @internal
 */
final class TableAdapterTest extends Unit
{
    public function testNormalizeCombinesRowsWithColumnKeys(): void
    {
        $adapter = $this->createAdapter($this->createTableDefinition([
            ['key' => 'fieldOfUse', 'label' => 'Field of use'],
            ['key' => 'distanceInMeter', 'label' => 'Distance'],
        ]));

        $this->assertSame(
            [
                ['fieldOfUse' => 'arable farming', 'distanceInMeter' => '20'],
                ['fieldOfUse' => 'orchards', 'distanceInMeter' => '5'],
            ],
            $adapter->normalize([
                ['arable farming', '20'],
                ['orchards', '5'],
            ])
        );
    }

    public function testNormalizePadsAndTruncatesRowsToColumnCount(): void
    {
        $adapter = $this->createAdapter($this->createTableDefinition([
            ['key' => 'a', 'label' => 'A'],
            ['key' => 'b', 'label' => 'B'],
        ]));

        $this->assertSame(
            [
                ['a' => '1', 'b' => null],
                ['a' => '1', 'b' => '2'],
            ],
            $adapter->normalize([
                ['1'],
                ['1', '2', 'surplus'],
            ])
        );
    }

    public function testNormalizeKeepsDataWithoutColumnConfig(): void
    {
        $adapter = $this->createAdapter(new Table());

        $this->assertSame(
            [['plain', 'rows']],
            $adapter->normalize([['plain', 'rows']])
        );
    }

    public function testNormalizeKeepsDataWithIntegerColumnsOnly(): void
    {
        $adapter = $this->createAdapter($this->createTableDefinition([
            ['key' => '0', 'label' => 'First'],
            ['key' => '1', 'label' => 'Second'],
        ]));

        $this->assertSame(
            [['plain', 'rows']],
            $adapter->normalize([['plain', 'rows']])
        );
    }

    private function createTableDefinition(array $columnConfig): Table
    {
        $table = new Table();
        $table->setColumnConfigActivated(true);
        $table->setColumnConfig($columnConfig);

        return $table;
    }

    private function createAdapter(Table $fieldDefinition): TableAdapter
    {
        $adapter = new TableAdapter(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(FieldDefinitionServiceInterface::class),
            $this->makeEmpty(IndexMappingServiceInterface::class),
        );
        $adapter->setFieldDefinition($fieldDefinition);

        return $adapter;
    }
}
