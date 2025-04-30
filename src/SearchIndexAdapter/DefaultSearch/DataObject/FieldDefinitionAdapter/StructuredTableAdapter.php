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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Model\DataObject\ClassDefinition\Data\StructuredTable;
use Pimcore\Normalizer\NormalizerInterface;

/**
 * @internal
 */
final class StructuredTableAdapter extends AbstractAdapter
{
    public function getIndexMapping(): array
    {
        $fieldDefinition = $this->getFieldDefinition();
        if (!$fieldDefinition instanceof StructuredTable) {
            throw new InvalidArgumentException(
                'FieldDefinition must be of type StructuredTable'
            );
        }

        $rows = $fieldDefinition->getRows();
        $cols = $fieldDefinition->getCols();
        $mapping = [];
        foreach ($rows as $row) {
            if (!$row['key']) {
                continue;
            }

            $columns = [];
            foreach ($cols as $col) {
                if (!$col['key']) {
                    continue;
                }

                $columns[$col['key']] = $this->getColumnValueMapping($col['type']);
            }

            $mapping[$row['key']] = [
                'type' => AttributeType::NESTED->value,
                'properties' => $columns,
            ];
        }

        return [
            'properties' => $mapping,
        ];

    }

    public function normalize(mixed $value): ?array
    {
        $fieldDefinition = $this->getFieldDefinition();
        if (!$fieldDefinition instanceof NormalizerInterface) {
            return null;
        }

        $returnValue = [];

        $rows = $fieldDefinition->normalize($value);
        foreach ($rows as $rowKey => $columns) {
            foreach ($columns as $colKey => $colValue) {
                if ($colKey !== '' && $rowKey !== '') {
                    $returnValue[$rowKey][$colKey] = $colValue;
                }
            }
        }

        return $returnValue;
    }

    private function getColumnValueMapping(string $columnType): array
    {
        return match (true) {
            $columnType === 'text' => [
                'type' => AttributeType::KEYWORD->value,
            ],
            $columnType === 'number' => [
                'type' => AttributeType::FLOAT->value,
            ],
            $columnType === 'bool' => [
                'type' => AttributeType::INTEGER->value,
            ],
            default => [
                'type' => AttributeType::TEXT->value,
            ]
        };
    }
}
