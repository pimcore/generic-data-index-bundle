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
                'object' => [
                    'type' => AttributeType::LONG->value,
                ],
                'asset' => [
                    'type' => AttributeType::LONG->value,
                ],
                'document' => [
                    'type' => AttributeType::LONG->value,
                ],
            ],
        ];
    }

    public function normalize(mixed $value): ?array
    {
        if($value instanceof ElementInterface) {
            return [
                Service::getElementType($value) => [$value->getId()],
            ];
        }

        return null;
    }

    protected function getSearchFilterFieldPath(AssetMetaDataFilter|AssetMetaDataAggregation $filter): string
    {
        return parent::getSearchFilterFieldPath($filter) . '.' . $filter->getType();
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
