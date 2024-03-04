# OpenSearch Search Models

OpenSearch search models can be used when individual OpenSearch queries are needed to streamline the creation of OpenSearch search JSONs.

This is especially useful when you want to create your own [search modifiers](../05_Search_Modifiers/README.md).

## Example usage in search modifier

This example shows how to use a custom search modifier to add a term filter to the search query.

```php
#[AsSearchModifierHandler]
public function handleCustomFilter(CustomFilter $customFilter, SearchModifierContextInterface $context): void
{
    $context->getSearch()->addQuery(
        new TermFilter(
            field: $customFilter->getField(),
            term: $customFilter->getValue(),
        )
    );
}
```

## Available OpenSearch search models

### Search

The search model is the main model to create a search query. It can be used to add queries, filters, aggregations and sorting to the search.

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search;
$search = (new Search())
    ->setSize(10) // set the number of results to return
    ->setFrom(0) // set the offset of the results
    ->setSource(['field']) // set the fields to return
    ->addSort(new FieldSort('field', 'asc')) // add a sort
    ->addQuery(new TermQuery('field', 'value')) // add a query
    ->addAggregation(new Aggregation('test-aggregation',[...])) // add an aggregation
;

$result = $openSearchClient->search( [
      'index' => $indexName,
      'body' => $search->toArray()
]);
```

### Query

The query model is used to create a query for the search. It can be used to create any query which is supported by OpenSearch.

#### BoolQuery

Represents a boolean query. It can be used to combine multiple queries with boolean operators. See OpenSearch [documentation](https://opensearch.org/docs/latest/query-dsl/compound/bool/) for more details.

##### Basic usage
```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery;

$boolQuery = new BoolQuery([
    'should' => [
        ['term' => ['field' => 'value']],
        ['term' => ['field2' => 'value2']],
    ],
]);
```

##### Add additional conditions
```php
$boolQuery = new BoolQuery();
$boolQuery->addCondition('must', [
    'term' => ['field' => 'value']
]);
```


##### Merge multiple queries
```php
$boolQueryA = new BoolQuery([
    'should' => [
        ['term' => ['field' => 'value']],
    ],
]);

$boolQueryB = new BoolQuery([
    'should' => [
        ['term' => ['field' => 'value']],
    ],
]);

// this will result in a query with two should conditions
$boolQueryA->merge($boolQueryB);
```

##### Use other queries in sub queries
```php
$boolQuery = new BoolQuery([
    'should' => [
        new TermFilter('field', 'value'),
        new TermFilter('field2', 'value2'),
    ]
]);
```

#### Generic Query

The generic `Query` model can be used to create any query which is supported by OpenSearch. It can be used to create custom queries which are not covered by the other query models.

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\Query;

$matchQuery = new Query('match', [
    'field' => 'value'
]);

$rangeQuery = new Query('range', [
    'field' => [
        'gte' => 10,
        'lte' => 20,
    ]
]);
```

#### TermFilter

The term filter combines a boolean query with a term query. It can be used to filter the search results by a term.

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\TermFilter;
$termFilter = new TermFilter('field', 'value');
```

#### TermsFilter

The terms filter combines a boolean query with a terms query. It can be used to filter the search results by multiple term.

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\TermsFilter;
$termFilter = new TermsFilter('field', ['value', 'value2']);

```#### DateFilter

The date filter can be used to filter the search results by a date range or exact date.

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\DateFilter;

// date range
$dateFilter = new DateFilter('datefield', strtotime('2000-01-01'), strtotime('2099-12-31'));

// exact date
$dateFilter = new DateFilter('datefield', null, null, strtotime('2000-01-01'));
```

The date filter rounds the timestamps to full days by default. If you want to use exact timestamps, you can set the `roundToDay` option to `false`.

```php
// exact timestamp
$dateFilter = new DateFilter('datefield', null, null, strtotime('2000-01-01 12:00:00'), false);
```

```php

### Aggregation

The aggregation model is used to create an aggregation for the search. It can be used to create any aggregation which is supported by OpenSearch. It's just a simple wrapper class without any special logic.

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Aggregation\Aggregation;

$aggregation = new Aggregation('test-aggregation', [
    'terms' => [
        'field' => 'value',
    ],
]);
```