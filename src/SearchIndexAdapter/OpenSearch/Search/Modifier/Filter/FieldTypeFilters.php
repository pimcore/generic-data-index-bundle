<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\Filter;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\DateFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchPqlFieldNameTransformationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query;

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
    ): void
    {
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
}