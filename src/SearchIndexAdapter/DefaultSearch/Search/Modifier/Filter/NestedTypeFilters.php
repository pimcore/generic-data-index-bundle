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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\MatchNoneQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\NestedFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\SimpleQueryStringFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\MappedFieldPathDeclaration;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\BooleanFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IntegerFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\NumberFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\BooleanMultiSelectFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\ClassificationStoreFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\DateFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\MultiSelectFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\NestedFilter as NestedFilterParam;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\NumberRangeFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\TimeFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\FullTextSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\WildcardSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\FullTextSearch\FullTextSearchHandlers;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\MappedFieldPathServiceInterface;

/**
 * @internal
 */
final readonly class NestedTypeFilters
{
    /**
     * The metadata field holding the concrete index a document lives in, which is how a query names
     * a subset of the indices behind an alias.
     */
    private const INDEX_META_FIELD = '_index';

    public function __construct(
        private BasicFilters $basicFilters,
        private FieldTypeFilters $fieldTypeFilters,
        private FullTextSearchHandlers $fullTextSearchHandlers,
        private MappedFieldPathServiceInterface $mappedFieldPathService
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
            new NestedFilter($this->buildFieldPrefix($filter->getFieldName()), $subQuery)
        );
    }

    /**
     * A filter asking for a *missing* value has its group, language node and key checked against the
     * mapping before the sub-modifier is built, and an address the mapping does not declare matches
     * nothing.
     *
     * This is what makes the documented guarantee - a node the key is not indexed under is an empty
     * result, not an error - hold for that filter too. A sub-modifier that compares a value to
     * something cannot match an absent path and gave the empty result on its own, but one that asks
     * for a missing value is `must_not exists` and an absent path *satisfies* it, so under a wrong
     * language node it returned every element that has the group at all - including the ones whose
     * value sits under the node the key really is indexed under, which is the opposite of the promise.
     *
     * The address is settled from the mapping rather than from the key's localization flag, which
     * keeps that rule in the one place that owns it: the mapping is what the write side declared from
     * it, so the read side never re-derives it and the two cannot disagree. It is also the only answer
     * that stays right on an index upgraded in place, where a language node dynamic mapping created
     * before `dynamic: false` is still declared and really does hold values - the flag alone would
     * rule out a node that works. On an index built from scratch the undeclared node is not filterable
     * by design, which the upgrade notes already state.
     *
     * The answer is kept per index, because a search can read more than one. A classless data object
     * search names the global alias - IndexNameResolver::resolveIndexName() falls back to it when the
     * search carries no class - so every class index is behind it at once, and a key that inherits the
     * localized field of one class while being flagged not localized in another is declared under the
     * language node of the first class's index only.
     *
     * One index declaring the address therefore cannot settle it for the rest. The filter is narrowed
     * to the indices that do declare it, which leaves the others contributing nothing instead of
     * contributing every object whose value sits under the node their own key is indexed under. The
     * narrowing is on `_index`, so it also rules out the indices of classes that have no such field at
     * all, which the nested clause cannot be built against.
     */
    #[AsSearchModifierHandler]
    public function handleClassificationStoreFilter(
        ClassificationStoreFilter $filter,
        SearchModifierContextInterface $context
    ): void {
        $declaration = $this->resolveMissingValueDeclaration($filter, $context->getOriginalSearch());

        if ($declaration->isUndeclaredEverywhere()) {
            $context->getSearch()->addQuery(new MatchNoneQuery());

            return;
        }

        $subQuery = $this->getSubQuery($filter, $context->getOriginalSearch());
        if ($subQuery === null) {
            return;
        }

        if ($declaration->isDeclaredByPartOfTheIndices()) {
            $context->getSearch()->addQuery(
                new TermsFilter(
                    field: self::INDEX_META_FIELD,
                    terms: $declaration->getDeclaringIndices()
                )
            );
        }

        $context->getSearch()->addQuery(
            new NestedFilter($this->buildFieldPrefix($filter->getFieldName()), $subQuery)
        );
    }

    /**
     * What the mappings say about the key a missing-value filter addresses, under the node it
     * addresses it in. Only such a filter is asked about, so nothing that already returned the empty
     * result changes behaviour or pays for a mapping lookup - every other sub-modifier gets the
     * unanswered declaration, whose predicates are both false.
     */
    private function resolveMissingValueDeclaration(
        ClassificationStoreFilter $filter,
        SearchInterface $search
    ): MappedFieldPathDeclaration {
        $keyName = $this->getMissingValueKeyName($filter->getSubModifier());
        if ($keyName === null) {
            return MappedFieldPathDeclaration::unanswered();
        }

        return $this->mappedFieldPathService->resolveDeclaration(
            $search,
            $this->buildStoreFieldPrefix($filter) . '.' . $keyName
        );
    }

    /**
     * The key of a sub-modifier that asks for a missing value, and null for every other one - which is
     * what decides whether the address has to be checked at all.
     *
     * These two are the whole set: a null `BooleanFilter` is `must_not exists`
     * ({@see BasicFilters::getBooleanQuery()}), and a null in a `BooleanMultiSelectFilter`'s value list
     * is the same clause, alone or in a `should` beside the terms
     * ({@see FieldTypeFilters::getBooleanMultiSelectFilter()}). Every other sub-modifier compares a
     * value to something and cannot match an absent path, so it gave the empty result already.
     * `MultiSelectFilter` can match a missing value too, but only for a top-level field - it keeps
     * exact matching under a prefix, which is the only way it is reached from here.
     */
    private function getMissingValueKeyName(SearchModifierInterface $subModifier): ?string
    {
        return match (true) {
            $subModifier instanceof BooleanFilter => $subModifier->getSearchTerm() === null
                ? $subModifier->getFieldName()
                : null,

            $subModifier instanceof BooleanMultiSelectFilter => in_array(
                null,
                $subModifier->getValues(),
                true
            ) ? $subModifier->getField() : null,

            default => null,
        };
    }

    private function getSubQuery(ClassificationStoreFilter|NestedFilterParam $filter, SearchInterface $search): ?array
    {
        $modifier = $filter->getSubModifier();
        $fieldName = $this->buildFieldPrefix($filter->getFieldName());
        if ($filter instanceof ClassificationStoreFilter) {
            $fieldName = $this->buildStoreFieldPrefix($filter);
        }

        return match (true) {
            $modifier instanceof BooleanFilter =>
            $this->basicFilters->getBooleanQuery($modifier, $fieldName, $search)->toArrayAsSubQuery(),

            $modifier instanceof IntegerFilter, $modifier instanceof NumberFilter =>
            $this->basicFilters->getNumberQuery($modifier, $fieldName, $search)->toArrayAsSubQuery(),

            $modifier instanceof DateFilter =>
            $this->fieldTypeFilters->getDateFilterQuery($modifier, $fieldName, $search)->toArray(true),

            $modifier instanceof TimeFilter =>
            $this->fieldTypeFilters->getTimeFilterQuery($modifier, $fieldName, $search)->toArray(true),

            $modifier instanceof MultiSelectFilter =>
            $this->fieldTypeFilters->getMultiSelectQuery($modifier, $fieldName, $search)->toArrayAsSubQuery(),

            $modifier instanceof BooleanMultiSelectFilter =>
            $this->fieldTypeFilters->getBooleanMultiSelectFilter($modifier, $fieldName, $search)?->toArrayAsSubQuery(),

            $modifier instanceof NumberRangeFilter =>
            $this->fieldTypeFilters->getNumberRangeFilter($modifier, $fieldName, $search)->toArray(true),

            $modifier instanceof WildcardSearch =>
            $this->fullTextSearchHandlers->getWildCardSubQuery($modifier, $fieldName, $search)
                ?->toArrayAsSubQuery(),

            $modifier instanceof FullTextSearch => $this->handleFullTextSearch($modifier),

            default => null
        };
    }

    private function buildFieldPrefix(string $fieldName): string
    {
        return  FieldCategory::STANDARD_FIELDS->value . '.' . $fieldName;
    }

    private function buildStoreFieldPrefix(ClassificationStoreFilter $filter): string
    {
        return sprintf(
            '%s.%s.%s.%s',
            FieldCategory::STANDARD_FIELDS->value,
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

        return (new SimpleQueryStringFilter(
            $modifier->getSearchTerm(),
            $modifier->getDefaultOperator(),
            $modifier->getFields(),
            $modifier->getFlags(),
        ))->toArrayAsSubQuery();
    }
}
