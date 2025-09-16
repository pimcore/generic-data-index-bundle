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
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\NestedFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\SimpleQueryStringFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\BooleanFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IntegerFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\ClassificationStoreFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\DateFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\MultiSelectFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\NestedFilter as NestedFilterParam;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\NumberRangeFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\FullTextSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\WildcardSearch;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\FullTextSearch\FullTextSearchHandlers;

/**
 * @internal
 */
final readonly class NestedTypeFilters
{
    public function __construct(
        private BasicFilters $basicFilters,
        private FieldTypeFilters $fieldTypeFilters,
        private FullTextSearchHandlers $fullTextSearchHandlers
    ) {
    }

    #[AsSearchModifierHandler]
    public function handleNestedFilter(
        NestedFilterParam $filter,
        SearchModifierContextInterface $context
    ): void {
        $subQuery = $this->getSubQuery($filter, $context->getOriginalSearch());
        if ($subQuery === null) {
            return;
        }

        $context->getSearch()->addQuery(
            new NestedFilter($this->buildPath($filter->getPath()), $subQuery)
        );
    }

    #[AsSearchModifierHandler]
    public function handleClassificationStoreFilter(
        ClassificationStoreFilter $filter,
        SearchModifierContextInterface $context
    ): void {
        $subQuery = $this->getSubQuery($filter, $context->getOriginalSearch());
        if ($subQuery === null) {
            return;
        }

        $context->getSearch()->addQuery(
            new NestedFilter($this->buildPath($filter->getFieldName()), $subQuery)
        );
    }

    private function getSubQuery(ClassificationStoreFilter|NestedFilterParam $filter, SearchInterface $search): ?array
    {
        $modifier = $filter->getSubModifier();
        $fieldName = $filter->getFieldName();
        if ($filter instanceof ClassificationStoreFilter) {
            $fieldName = $this->buildStoreFieldPrefix($filter);
        }

        return match (true) {
            $modifier instanceof BooleanFilter =>
            $this->basicFilters->getBooleanQuery($modifier, $fieldName, $search)->toArrayAsSubQuery(),

            $modifier instanceof IntegerFilter =>
            $this->basicFilters->getIntegerQuery($modifier, $fieldName, $search)->toArrayAsSubQuery(),

            $modifier instanceof DateFilter =>
            $this->fieldTypeFilters->getDateFilterQuery($modifier, $fieldName, $search)->toArray(true),

            $modifier instanceof MultiSelectFilter =>
            $this->fieldTypeFilters->getMultiSelectQuery($modifier, $fieldName, $search)->toArrayAsSubQuery(),

            $modifier instanceof NumberRangeFilter =>
            $this->fieldTypeFilters->getNumberRangeFilter($modifier, $fieldName, $search)->toArray(true),

            $modifier instanceof WildcardSearch =>
            $this->fullTextSearchHandlers->getWildCardSubQuery($modifier, $fieldName, $search)
                ?->toArrayAsSubQuery(),

            $modifier instanceof FullTextSearch => $this->handleFullTextSearch($modifier),

            default => null
        };
    }

    private function buildPath(string $fieldName): string
    {
        return 'standard_fields.' . $fieldName;
    }

    private function buildStoreFieldPrefix(ClassificationStoreFilter $filter): string
    {
        return sprintf(
            'standard_fields.%s.%s.%s',
            $filter->getFieldName(),
            $filter->getGroup(),
            $filter->getLocale(),
        );
    }

    private function handleFullTextSearch(FullTextSearch $modifier): ?array
    {
        if (empty($modifier->getSearchTerm())) {
            return null;
        }

        return (new SimpleQueryStringFilter($modifier->getSearchTerm()))->toArrayAsSubQuery();
    }
}
