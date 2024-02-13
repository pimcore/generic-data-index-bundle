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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter;

use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Model\DataObject\ClassDefinition\Data\AdvancedManyToManyObjectRelation;
use Pimcore\Model\DataObject\ClassDefinition\Data\AdvancedManyToManyRelation;

/**
 * @internal
 */
final class AdvancedManyToManyRelationAdapter extends AbstractAdapter
{
    public function getOpenSearchMapping(): array
    {
        $fieldDefinition = $this->getFieldDefinition();

        if (!$fieldDefinition instanceof AdvancedManyToManyRelation &&
            !$fieldDefinition instanceof AdvancedManyToManyObjectRelation) {
            throw new InvalidArgumentException(
                'FieldDefinition must be of type AdvancedManyToManyRelation or AdvancedManyToManyObjectRelation'
            );
        }

        $columnDefinition = $this->getColumnDefinition($fieldDefinition->getColumns());

        return [
            'type' => AttributeType::NESTED,
            'properties' => [
                'fieldname' => [
                    'type' => AttributeType::KEYWORD,
                ],
                'columns' => [
                    'type' => AttributeType::KEYWORD, // Is actually an array of strings
                ],
                'element' => [
                    'properties' => [
                        'id' => [
                            'type' => AttributeType::LONG,
                        ],
                        'type' => [
                            'type' => AttributeType::KEYWORD,
                        ],
                    ],
                ],
                'data' => [
                    'properties' => $columnDefinition,
                ],
            ],
        ];
    }

    private function getColumnDefinition(array $columns): array
    {
        $type = [];
        foreach ($columns as $column) {
            if (isset($column['type'], $column['key'])) {
                match ($column['type']) {
                    'number' => $type[$column['key']] = [
                        'type' => AttributeType::LONG,
                    ],
                    default => $type[$column['key']] = [
                        'type' => AttributeType::KEYWORD,
                    ]
                };
            }
        }

        return $type;
    }
}
