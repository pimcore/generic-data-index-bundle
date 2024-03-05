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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Asset\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Asset\AssetMetaDataAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Asset\AssetMetaDataFilter;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;
use Pimcore\ValueObject\Collection\ArrayOfIntegers;

/**
 * @internal
 */
final class RelationAdapter extends AbstractAdapter
{
    public function getIndexMapping(): array
    {
        return [
            'properties' => [
                'id' => [
                    'type' => AttributeType::LONG->value,
                ],
                'type' => [
                    'type' => AttributeType::KEYWORD->value,
                ],
            ],
        ];
    }

    public function normalize(mixed $value): ?array
    {
        if($value instanceof ElementInterface) {
            return [
                'type' => Service::getElementType($value),
                'id' => $value->getId(),
            ];
        }

        return null;
    }

    protected function getSearchFilterFieldPath(AssetMetaDataFilter|AssetMetaDataAggregation $filter): string
    {
        return parent::getSearchFilterFieldPath($filter) . '.id';
    }

    protected function isValidScalar(mixed $value): bool
    {
        return is_int($value);
    }

    protected function validateArray(array $value): void
    {
        new ArrayOfIntegers($value);
    }
}
