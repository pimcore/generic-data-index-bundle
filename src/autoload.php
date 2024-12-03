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

// TODO: remove this autoload (and from composer) in version 2.0
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\QueryType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\WildcardFilterMode;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType as OpenSearchAttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\ConditionType as OpenSearchConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\QueryType as OpenSearchQueryType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\WildcardFilterMode as OpenSearchWildcardFilterMode;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\SearchFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\OpenSearch\SearchFailedException as OpenSearchSearchFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Aggregation\Aggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Aggregation\AggregationList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Debug\SearchInformation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\DefaultSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContext;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\AsSubQueryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\DateFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\Query;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\QueryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\QueryList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\SimpleQueryStringFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\WildcardFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSortList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Traits\QueryObjectsToArrayTrait;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Traits\SimplifySingleTypesTrait;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Aggregation\Aggregation as OpenSearchAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Aggregation\AggregationList as OpenSearchAggregationList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Debug\SearchInformation as OpenSearchSearchInformation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContext as OpenSearchModifierContext;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface as OpenSearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\OpenSearchSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\AsSubQueryInterface as OpenSearchAsSubQueryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery as OpenSearchBoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\DateFilter as OpenSearchDateFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\Query as OpenSearchQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\QueryInterface as OpenSearchQueryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\QueryList as OpenSearchQueryList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\SimpleQueryStringFilter as OpenSearchSimpleQueryStringFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\TermFilter as OpenSearchTermFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\TermsFilter as OpenSearchTermsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\WildcardFilter as OpenSearchWildcardFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search as OpenSearchSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort\FieldSort as OpenSearchFieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort\FieldSortList as OpenSearchFieldSortList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Traits\QueryObjectsToArrayTrait as OpenSearchQueryObjectsToArrayTrait;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Traits\SimplifySingleTypesTrait as OpenSearchSimplifySingleTypesTrait;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Asset\FieldDefinitionAdapter\AbstractAdapter as AssetAbstractAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Asset\FieldDefinitionAdapter\BooleanAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Asset\FieldDefinitionAdapter\DateAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Asset\FieldDefinitionAdapter\KeywordAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Asset\FieldDefinitionAdapter\RelationAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Asset\FieldDefinitionAdapter\TextKeywordAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\AbstractAdapter as DataObjectAbstractAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Asset\FieldDefinitionAdapter\AbstractAdapter as OpenSearchAssetAbstractAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\DataObject\FieldDefinitionAdapter\AbstractAdapter as OpenSearchDataObjectAbstractAdapter;

const ASSET_FIELD_DEFINITION_ADAPTER_NAMESPACE = 'Pimcore\\Bundle\GenericDataIndexBundle\\SearchIndexAdapter\\OpenSearch\\Asset\\FieldDefinitionAdapter\\';

$classesToAlias = [
    AttributeType::class => OpenSearchAttributeType::class,
    ConditionType::class => OpenSearchConditionType::class,
    QueryType::class => OpenSearchQueryType::class,
    WildcardFilterMode::class => OpenSearchWildcardFilterMode::class,
    SearchFailedException::class => OpenSearchSearchFailedException::class,
    Aggregation::class => OpenSearchAggregation::class,
    AggregationList::class => OpenSearchAggregationList::class,
    SearchInformation::class => OpenSearchSearchInformation::class,
    SearchModifierContext::class => OpenSearchModifierContext::class,
    SearchModifierContextInterface::class => OpenSearchModifierContextInterface::class,
    AsSubQueryInterface::class => OpenSearchAsSubQueryInterface::class,
    BoolQuery::class => OpenSearchBoolQuery::class,
    DateFilter::class => OpenSearchDateFilter::class,
    Query::class => OpenSearchQuery::class,
    QueryInterface::class => OpenSearchQueryInterface::class,
    QueryList::class => OpenSearchQueryList::class,
    SimpleQueryStringFilter::class => OpenSearchSimpleQueryStringFilter::class,
    TermFilter::class => OpenSearchTermFilter::class,
    TermsFilter::class => OpenSearchTermsFilter::class,
    WildcardFilter::class => OpenSearchWildcardFilter::class,
    FieldSort::class => OpenSearchFieldSort::class,
    FieldSortList::class => OpenSearchFieldSortList::class,
    QueryObjectsToArrayTrait::class => OpenSearchQueryObjectsToArrayTrait::class,
    SimplifySingleTypesTrait::class => OpenSearchSimplifySingleTypesTrait::class,
    Search::class => OpenSearchSearch::class,
    DefaultSearchInterface::class => OpenSearchSearchInterface::class,
    AssetAbstractAdapter::class => OpenSearchAssetAbstractAdapter::class,
    DataObjectAbstractAdapter::class => OpenSearchDataObjectAbstractAdapter::class,
    BooleanAdapter::class => ASSET_FIELD_DEFINITION_ADAPTER_NAMESPACE . 'BooleanAdapter',
    DateAdapter::class => ASSET_FIELD_DEFINITION_ADAPTER_NAMESPACE . 'DateAdapter',
    KeywordAdapter::class => ASSET_FIELD_DEFINITION_ADAPTER_NAMESPACE . 'KeywordAdapter',
    RelationAdapter::class => ASSET_FIELD_DEFINITION_ADAPTER_NAMESPACE . 'RelationAdapter',
    TextKeywordAdapter::class => ASSET_FIELD_DEFINITION_ADAPTER_NAMESPACE . 'TextKeywordAdapter',
];

foreach ($classesToAlias as $originalClass => $aliasClass) {
    class_alias($originalClass, $aliasClass);
}
