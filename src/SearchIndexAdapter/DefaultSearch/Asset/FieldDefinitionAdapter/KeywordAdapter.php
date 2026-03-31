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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Asset\FieldDefinitionAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Aggregation\Aggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Asset\AssetMetaDataAggregation;

/**
 * @internal
 */
final class KeywordAdapter extends AbstractAdapter
{
    public function getIndexMapping(): array
    {
        return [
            'type' => AttributeType::KEYWORD->value,
            'ignore_above' => 8191,
            'fields' => [
                'sort' => [
                    'type' => AttributeType::KEYWORD->value,
                    'ignore_above' => 8191,
                    'normalizer' => 'generic_data_index_sort_truncate_normalizer',
                ],
            ],
        ];
    }

    public function getSearchFilterAggregation(AssetMetaDataAggregation $aggregation): Aggregation
    {
        return new Aggregation($aggregation->getAggregationName(), [
            'terms' => [
                'field' => $this->getSearchFilterFieldPath($aggregation),
            ],
        ]);
    }
}
