<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\DataObject\FieldDefinitionAdapter;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter\AdvancedManyToManyRelationAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\AdvancedManyToManyRelation;
use Pimcore\Model\DataObject\ClassDefinition\Data\Checkbox;

/**
 * @internal
 */
final class AdvancedManyToManyRelationAdapterTest extends Unit
{
    public function testGetOpenSearchMapping(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = new AdvancedManyToManyRelationAdapter($searchIndexConfigServiceInterfaceMock);
        $relation = new AdvancedManyToManyRelation();
        $relation->setColumns([
            [
                'type' => 'number',
                'key' => 'key1',
                'position' => 1,
            ],
            [
                'type' => 'select',
                'key' => 'key2',
                'position' => 1,
            ], ]);
        $adapter->setFieldDefinition($relation);

        $this->assertSame([
            'properties' => [
                'fieldname' => [
                    'type' => AttributeType::KEYWORD,
                ],
                'columns' => [
                    'type' => AttributeType::KEYWORD,
                ],
                'element' => [
                    'properties' => [
                        'id' => [
                            'type' => AttributeType::LONG,
                        ],
                        'type' => [
                            'type' => AttributeType::TEXT,
                        ],
                    ],
                ],
                'data' => [
                    'properties' => [
                        'key1' => [
                            'type' => AttributeType::LONG,
                        ],
                        'key2' => [
                            'type' => AttributeType::KEYWORD,
                        ],
                    ],
                    ],
                ],
            ], $adapter->getOpenSearchMapping());
    }

    public function testGetOpenSearchMappingException(): void
    {
        $searchIndexConfigServiceInterfaceMock = $this->makeEmpty(SearchIndexConfigServiceInterface::class);
        $adapter = new AdvancedManyToManyRelationAdapter($searchIndexConfigServiceInterfaceMock);
        $relation = new Checkbox();
        $adapter->setFieldDefinition($relation);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('FieldDefinition must be of type AdvancedManyToManyRelation');
        $adapter->getOpenSearchMapping();
    }
}
