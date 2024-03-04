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
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Aggregation\Aggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Asset\AssetMetaDataAggregation;

/**
 * @internal
 */
final class KeywordAdapter extends AbstractAdapter
{
    public function getIndexMapping(): array
    {
        return [
            'type' => AttributeType::KEYWORD->value
        ];
    }

    public function getSearchFilterAggregation(AssetMetaDataAggregation $aggregation): ?Aggregation
    {
        return new Aggregation($aggregation->getAggregationName(), [
            'terms' => [
                'field' => $this->getSearchFilterFieldPath($aggregation),
            ],
        ]);
    }
}