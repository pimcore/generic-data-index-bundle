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
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\BoolExistsQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\DateFilter as DateFilterQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TimeFilter as TimeFilterQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\MultiBoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\Query as QueryFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermsFilter as TermsFilterQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\BooleanMultiSelectFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\DateFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\MultiSelectFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\NumberRangeFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\TimeFilter;
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
        $context->getSearch()->addQuery($this->getDateFilterQuery(
            $dateFilter,
            null,
            $context->getOriginalSearch())
        );
    }

    public function getDateFilterQuery(
        DateFilter $dateFilter,
        ?string $prefix = null,
        ?SearchInterface $search = null
    ): DateFilterQuery {
        $fieldName = $dateFilter->getField();
        if ($prefix) {
            $fieldName = $prefix . '.' . $fieldName;
        }
        if ($search && $dateFilter->isPqlFieldNameResolutionEnabled()) {
            $fieldName = $this->fieldNameTransformationService->transformFieldnameForSearch($search, $fieldName);
        }

        return new DateFilterQuery(
            $fieldName,
            $dateFilter->getStartDate(),
            $dateFilter->getEndDate(),
            $dateFilter->getOnDate(),
            $dateFilter->isRoundToDay()
        );
    }

    #[AsSearchModifierHandler]
    public function handleTimeFilter(
        TimeFilter $timeFilter,
        SearchModifierContextInterface $context
    ): void {
        $context->getSearch()->addQuery($this->getTimeFilterQuery(
            $timeFilter,
            null,
            $context->getOriginalSearch())
        );
    }

    public function getTimeFilterQuery(
        TimeFilter $timeFilter,
        ?string $prefix = null,
        ?SearchInterface $search = null
    ): TimeFilterQuery {
        $fieldName = $timeFilter->getField();
        if ($prefix) {
            $fieldName = $prefix . '.' . $fieldName;
        }
        if ($search && $timeFilter->isPqlFieldNameResolutionEnabled()) {
            $fieldName = $this->fieldNameTransformationService->transformFieldnameForSearch($search, $fieldName);
        }

        return new TimeFilterQuery(
            $fieldName,
            $timeFilter->getStartTime(),
            $timeFilter->getEndTime(),
            $timeFilter->getOnTime(),
        );
    }

    #[AsSearchModifierHandler]
    public function handleMultiSelectFilter(
        MultiSelectFilter $multiSelectFilter,
        SearchModifierContextInterface $context
    ): void {
        $context->getSearch()->addQuery(
            $this->getMultiSelectQuery($multiSelectFilter, null, $context->getOriginalSearch())
        );
    }

    public function getMultiSelectQuery(
        MultiSelectFilter $multiSelectFilter,
        ?string $prefix = null,
        ?SearchInterface $search = null
    ): TermsFilterQuery {
        $fieldName = $multiSelectFilter->getField();
        if ($prefix) {
            $fieldName = $prefix . '.' . $fieldName;
        }
        if ($search && $multiSelectFilter->isPqlFieldNameResolutionEnabled()) {
            $fieldName = $this->fieldNameTransformationService->transformFieldnameForSearch($search, $fieldName);
        }

        return new TermsFilterQuery(
            $fieldName,
            $multiSelectFilter->getValues(),
        );
    }

    #[AsSearchModifierHandler]
    public function handleBooleanMultiSelectFilter(
        BooleanMultiSelectFilter $filter,
        SearchModifierContextInterface $context
    ): void {
        $context->getSearch()->addQuery(
            $this->getBooleanMultiSelectFilter($filter, null, $context->getOriginalSearch())
        );
    }

    public function getBooleanMultiSelectFilter(
        BooleanMultiSelectFilter $filter,
        ?string $prefix = null,
        ?SearchInterface $search = null
    ): null|BoolExistsQuery|MultiBoolQuery|TermsFilterQuery {
        if (count($filter->getValues()) === 0) {
            return null;
        }

        $fieldName = $filter->getField();
        if ($prefix) {
            $fieldName = $prefix . '.' . $fieldName;
        }

        if ($search && $filter->isPqlFieldNameResolutionEnabled()) {
            $fieldName = $this->fieldNameTransformationService->transformFieldnameForSearch($search, $fieldName);
        }

        $hasNull = in_array(null, $filter->getValues(), true);
        $nonNullValues = array_values(array_filter($filter->getValues(), static fn ($v) => $v !== null));

        return match (true) {
            $hasNull && $nonNullValues !== [] => new MultiBoolQuery($fieldName, $nonNullValues),
            $hasNull => new BoolExistsQuery($fieldName),
            default => new TermsFilterQuery($fieldName, $nonNullValues),
        };
    }

    #[AsSearchModifierHandler]
    public function handleNumberRangeFilter(
        NumberRangeFilter $numberRangeFilter,
        SearchModifierContextInterface $context
    ): void {

        $context->getSearch()->addQuery(
            $this->getNumberRangeFilter($numberRangeFilter, null, $context->getOriginalSearch())
        );
    }

    public function getNumberRangeFilter(
        NumberRangeFilter $numberRangeFilter,
        ?string $prefix = null,
        ?SearchInterface $search = null
    ): QueryFilter {
        $fieldName = $numberRangeFilter->getField();
        if ($prefix) {
            $fieldName = $prefix . '.' . $fieldName;
        }
        if ($search && $numberRangeFilter->isPqlFieldNameResolutionEnabled()) {
            $fieldName = $this->fieldNameTransformationService->transformFieldnameForSearch($search, $fieldName);
        }

        return new QueryFilter(
            'range',
            [
                $fieldName => [
                    'gt' => $numberRangeFilter->getMin(),
                    'lt' => $numberRangeFilter->getMax(),
                ],
            ]
        );
    }
}
