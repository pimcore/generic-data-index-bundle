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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\Aggregation;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\Search\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Aggregation\Aggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Tree\ChildFolderAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Tree\ChildrenCountAggregation;

/**
 * @internal
 */
final class TreeAggregations
{
    #[AsSearchModifierHandler]
    public function handleChildrenCountAggregation(
        ChildrenCountAggregation $aggregation,
        SearchModifierContextInterface $context
    ): void {
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

    #[AsSearchModifierHandler]
    public function handleChildFolderAggregation(
        ChildFolderAggregation $aggregation,
        SearchModifierContextInterface $context
    ): void {
        $context->getSearch()
            ->addAggregation(
                new Aggregation(
                    name: $aggregation->getAggregationName(),
                    params: [
                        'nested' => [
                            'path' => SystemField::PATH_LEVELS->getPath(),
                        ],
                        'aggs' => [
                            'filtered_level' => [
                                'filter' => [
                                    'term' => [
                                        SystemField::PATH_LEVELS->getPath()
                                            . '.level'
                                            => $aggregation->getChildLevel(),
                                    ],
                                ],
                                'aggs' => [
                                    'folder_names' => [
                                        'terms' => [
                                            'field' => SystemField::PATH_LEVELS->getPath()
                                                . '.name',
                                            'size' => $aggregation->getMaxFolders(),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                )
            )
            ->setSize(0)
        ;
    }
}
