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
use Pimcore\Normalizer\NormalizerInterface;

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
            'properties' => [
                'asset' => [
                    'type' => AttributeType::LONG,
                ],
                'object' => [
                    'type' => AttributeType::LONG,
                ],
                'document' => [
                    'type' => AttributeType::LONG,
                ],
                'details' => [
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

    public function normalize(mixed $value): mixed
    {
        $fieldDefinition = $this->getFieldDefinition();
        if (!$fieldDefinition instanceof NormalizerInterface) {
            return null;
        }

        $normalizedValues = $fieldDefinition->normalize($value);
        $returnValue = [
            'object' => [],
            'asset' => [],
            'document' => [],
        ];

        foreach ($normalizedValues as $normalizedValue) {
            if (isset($normalizedValue['element']['type'], $normalizedValue['element']['id'])) {
                $returnValue[$normalizedValue['element']['type']][] = $normalizedValue['element']['id'];
            }
        }

        $returnValue['details'] = $normalizedValues;

        return $returnValue;
    }
}
