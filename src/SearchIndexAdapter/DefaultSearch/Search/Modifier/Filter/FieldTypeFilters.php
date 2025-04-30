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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\Filter;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\Search\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\DateFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\MultiSelectFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\NumberRangeFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchPqlFieldNameTransformationServiceInterface;

/**
 * @internal
 */
final readonly class FieldTypeFilters
{
    public function __construct(
        private SearchPqlFieldNameTransformationServiceInterface $fieldNameTransformationService,
    ) {
    }

    #[AsSearchModifierHandler]
    public function handleDateFilter(
        DateFilter $dateFilter,
        SearchModifierContextInterface $context
    ): void {
        $fieldName = $dateFilter->getField();
        if ($dateFilter->isPqlFieldNameResolutionEnabled()) {
            $fieldName = $this->fieldNameTransformationService->transformFieldnameForSearch(
                $context->getOriginalSearch(),
                $fieldName
            );
        }

        $context->getSearch()->addQuery(
            new Query\DateFilter(
                $fieldName,
                $dateFilter->getStartDate(),
                $dateFilter->getEndDate(),
                $dateFilter->getOnDate(),
                $dateFilter->isRoundToDay()
            )
        );
    }

    #[AsSearchModifierHandler]
    public function handleMultiSelectFilter(
        MultiSelectFilter $multiSelectFilter,
        SearchModifierContextInterface $context
    ): void {
        $fieldName = $multiSelectFilter->getField();
        if ($multiSelectFilter->isPqlFieldNameResolutionEnabled()) {
            $fieldName = $this->fieldNameTransformationService->transformFieldnameForSearch(
                $context->getOriginalSearch(),
                $fieldName
            );
        }

        $context->getSearch()->addQuery(
            new Query\TermsFilter(
                $fieldName,
                $multiSelectFilter->getValues()
            )
        );
    }

    #[AsSearchModifierHandler]
    public function handleNumberRangeFilter(
        NumberRangeFilter $numberRangeFilter,
        SearchModifierContextInterface $context
    ): void {
        $fieldName = $numberRangeFilter->getField();
        if ($numberRangeFilter->isPqlFieldNameResolutionEnabled()) {
            $fieldName = $this->fieldNameTransformationService->transformFieldnameForSearch(
                $context->getOriginalSearch(),
                $fieldName
            );
        }

        $context->getSearch()->addQuery(
            new Query\Query('range', [
                $fieldName => [
                    'gte' => $numberRangeFilter->getMin(),
                    'lte' => $numberRangeFilter->getMax(),
                ],
            ])
        );

    }
}
