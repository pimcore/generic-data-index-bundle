# Default Search Models

Default search models build OpenSearch/Elasticsearch query JSON programmatically.
Use them when building custom [search modifiers](../05_Search_Modifiers/README.md)
or when executing searches directly through the search client. The Generic Data Index
search services use these models internally.

## Example: Custom Search Modifier

Add a term filter to the search query in a custom search modifier handler:

```php
#[AsSearchModifierHandler]
public function handleCustomFilter(
    CustomFilter $customFilter,
    SearchModifierContextInterface $context
): void {
    $context->getSearch()->addQuery(
        new TermFilter(
            field: $customFilter->getField(),
            term: $customFilter->getValue(),
        )
    );
}
```

## Search Model

The `Search` model is the top-level container for building a search query.
Add queries, aggregations, sorting, and control pagination:

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;

$search = (new Search())
    ->setSize(10)
    ->setFrom(0)
    ->setSource(['field'])
    ->addSort(new FieldSort('field', 'asc'))
    ->addQuery(new TermQuery('field', 'value'))
    ->addAggregation(new Aggregation('test-aggregation', [...]));

$result = $searchClient->search([
    'index' => $indexName,
    'body' => $search->toArray()
]);
```

## Query Models

### BoolQuery

Combines multiple queries with boolean operators.
See [OpenSearch](https://opensearch.org/docs/latest/query-dsl/compound/bool/)
or [Elasticsearch](https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-bool-query.html)
documentation.

#### Basic usage

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\BoolQuery;

$boolQuery = new BoolQuery([
    'should' => [
        ['term' => ['field' => 'value']],
        ['term' => ['field2' => 'value2']],
    ],
]);
```

#### Adding conditions

```php
$boolQuery = new BoolQuery();
$boolQuery->addCondition('must', [
    'term' => ['field' => 'value']
]);
```

#### Merging queries

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

// Results in a query with two "should" conditions
$boolQueryA->merge($boolQueryB);
```

#### Using query objects in sub-queries

```php
$boolQuery = new BoolQuery([
    'should' => [
        new TermFilter('field', 'value'),
        new TermFilter('field2', 'value2'),
    ]
]);
```

### TermFilter

Combines a boolean query with a term query to filter results by an exact term:

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermFilter;

$termFilter = new TermFilter('field', 'value');
```

### TermsFilter

Combines a boolean query with a terms query to filter by multiple terms.

### WildcardFilter

Combines a boolean query with a wildcard query. Use `*` as wildcard:

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\WildcardFilter;

$wildcardFilter = new WildcardFilter('field', 'value*');
```

See the `WildcardFilter` constructor for additional options.

### DateFilter

Filter results by a date range or exact date:

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\DateFilter;

// Date range
$dateFilter = new DateFilter('datefield', strtotime('2000-01-01'), strtotime('2099-12-31'));

// Exact date
$dateFilter = new DateFilter('datefield', null, null, strtotime('2000-01-01'));
```

By default, timestamps are rounded to full days. Disable rounding for exact timestamps:

```php
$dateFilter = new DateFilter(
    'datefield', null, null, strtotime('2000-01-01 12:00:00'), false
);
```

### Generic Query

Build any query supported by OpenSearch or Elasticsearch:

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\Query;

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

## Aggregation Model

Build any aggregation supported by OpenSearch or Elasticsearch:

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Aggregation\Aggregation;

$aggregation = new Aggregation('test-aggregation', [
    'terms' => [
        'field' => 'value',
    ],
]);
```
