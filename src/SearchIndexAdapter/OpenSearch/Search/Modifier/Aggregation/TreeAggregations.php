<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\Aggregation;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Aggregation\Aggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\TermsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Tree\ChildrenCountAggregation;

final class TreeAggregations
{
    #[AsSearchModifierHandler]
    public function handleChildrenCountAggregation(
        ChildrenCountAggregation $aggregation,
        SearchModifierContextInterface $context
    ): void
    {
        $context->getSearch()
            ->addQuery(
                new TermsFilter(
                    field: SystemField::PARENT_ID->getPath(),
                    terms: $aggregation->getParentIds(),
                )
            )
            ->addAggregation(
                new Aggregation(
                    name: $aggregation->getAggregationName(),
                    params: [
                        'terms' => [
                            'field' => SystemField::PARENT_ID->getPath(),
                            'size' => count($aggregation->getParentIds()),
                        ],
                    ]
                )
            )
            ->setSize(0)
        ;
    }
}