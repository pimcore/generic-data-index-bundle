---
title: Search Modifiers
description: Filter, sort, and aggregate search results using built-in and custom search modifiers.
---

# Search Modifiers

Search modifiers filter, sort, and aggregate search results by altering the
underlying query. Add them via the `addModifier()` method:

```php
$search->addModifier(new ParentIdFilter(1));
```

## Available Search Modifiers

### Filters

| Modifier | Category | Description |
|----------|----------|-------------|
| [IdFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/Basic/IdFilter.php) | Basic | Filter by element ID |
| [IdsFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/Basic/IdsFilter.php) | Basic | Filter by multiple element IDs |
| [BooleanFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.x/src/Model/Search/Modifier/Filter/Basic/BooleanFilter.php) | Basic | Filter boolean fields. Supports [PQL field name resolution](#pql-field-name-resolution). |
| [IntegerFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/Basic/IntegerFilter.php) | Basic | Filter integer fields. Supports [PQL field name resolution](#pql-field-name-resolution). |
| [NumberFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.x/src/Model/Search/Modifier/Filter/Basic/NumberFilter.php) | Basic | Filter number fields. Supports [PQL field name resolution](#pql-field-name-resolution). |
| [ExcludeFoldersFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/Basic/ExcludeFoldersFilter.php) | Basic | Exclude folders from results |
| [ExcludeVariantsFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.x/src/Model/Search/Modifier/Filter/Basic/ExcludeVariantsFilter.php) | Basic | Exclude data object variants from results |
| [ParentIdsFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/Tree/ParentIdsFilter.php) | Tree | Filter by one or more parent IDs |
| [PathFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/Tree/PathFilter.php) | Tree | Filter by path (all levels or direct children, with or without parent) |
| [ClassIdsFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/Tree/ClassIdsFilter.php) | Tree | Filter objects by class IDs (optionally include folders). Set `$useClassName` to `true` to filter by class name instead. |
| [TagFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/Tree/TagFilter.php) | Tree | Filter by tag IDs (optionally include child tags) |
| [AssetMetaDataFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/Asset/AssetMetaDataFilter.php) | Asset | Filter by asset metadata attribute. The `$data` format depends on the metadata type and its [field definition adapter](https://github.com/pimcore/generic-data-index-bundle/tree/1.x/src/SearchIndexAdapter/DefaultSearch/Asset/FieldDefinitionAdapter). |
| [WorkspaceQuery](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/Workspaces/WorkspaceQuery.php) | Workspace | Filter by user workspaces and permissions for a single element type (added to asset/document/data object searches by default) |
| [ElementWorkspacesQuery](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/Workspaces/WorkspaceQuery.php) | Workspace | Filter by user workspaces across all element types (added to element search by default) |
| [MultiSelectFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/FieldType/MultiSelectFilter.php) | Field type | Filter text fields by exact string list. Supports [PQL field name resolution](#pql-field-name-resolution). |
| [BooleanMultiSelectFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.x/src/Model/Search/Modifier/Filter/FieldType/BooleanMultiSelectFilter.php) | Field type | Filter boolean fields by value list (true, false, null). Supports [PQL field name resolution](#pql-field-name-resolution). |
| [DateFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/FieldType/DateFilter.php) | Field type | Filter date fields by exact date or date range. Supports [PQL field name resolution](#pql-field-name-resolution). |
| [ClassificationStoreFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.x/src/Model/Search/Modifier/Filter/FieldType/ClassificationStoreFilter.php) | Nested | Filter by classification store field values. Requires a sub-modifier matching the field type. |
| [NestedFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.x/src/Model/Search/Modifier/Filter/FieldType/NestedFilter.php) | Nested | Filter nested fields. Requires a sub-modifier matching the nested field type. |

### Full Text Search Queries

| Modifier | Description |
|----------|-------------|
| [ElementKeySearch](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/FullTextSearch/ElementKeySearch.php) | Search by element key (as in Pimcore Studio) with [wildcard support](#wildcard-support) |
| [FullTextSearch](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/FullTextSearch/FullTextSearch.php) | Search all element fields using simple query string syntax ([OpenSearch](https://opensearch.org/docs/latest/query-dsl/full-text/simple-query-string/#simple-query-string-syntax) / [Elasticsearch](https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-simple-query-string-query.html#simple-query-string-syntax)) |
| [MultiMatchSearch](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/FullTextSearch/MultiMatchSearch.php) | Search with configurable fields, match type (best_fields, most_fields, cross_fields, phrase, phrase_prefix), and operator (or/and). See [OpenSearch](https://opensearch.org/docs/latest/query-dsl/full-text/multi-match/) / [Elasticsearch](https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-multi-match-query.html) docs. |
| [WildcardSearch](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/FullTextSearch/WildcardSearch.php) | Filter text fields with [wildcard support](#wildcard-support) and [PQL field name resolution](#pql-field-name-resolution) |

### Dependencies

| Modifier | Description |
|----------|-------------|
| [RequiresFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/Dependency/RequiresFilter.php) | Get all elements that the given element requires |
| [RequiredByFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/Dependency/RequiredByFilter.php) | Get all elements required by the given element |

### Query Language

| Modifier | Description |
|----------|-------------|
| [PqlFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/QueryLanguage/PqlFilter.php) | Apply a [Pimcore Query Language (PQL)](../09_Pimcore_Query_Language/README.md) condition |

### Sort Modifiers

When multiple sort modifiers are added, they apply in order: the first modifier
is the primary sort, the second is the secondary sort, and so on.

| Modifier | Category | Description |
|----------|----------|-------------|
| [OrderByFullPath](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Sort/Tree/OrderByFullPath.php) | Tree | Sort by full path (including element key) |
| [OrderByField](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Sort/OrderByField.php) | Field | Sort by field name. With `$enablePqlFieldNameResolution` set to `true` (default), short field names resolve automatically via PQL logic. |
| [OrderByPageNumber](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Sort/Tree/OrderByPageNumber.php) | Search | Inverted search for large result sets. Applied automatically when results exceed 1,000 and the current page is past the halfway point. Requires existing sorting. |
| [OrderByIndexField](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Sort/Tree/OrderByIndexField.php) | Search | Sort by element tree index for custom tree ordering. Applies to data objects and documents only. |

### Aggregations

| Modifier | Category | Description |
|----------|----------|-------------|
| [ChildrenCountAggregation](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Aggregation/Tree/ChildrenCountAggregation.php) | Tree | Get children counts for given element IDs |
| [AssetMetaDataAggregation](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Aggregation/Asset/AssetMetaDataAggregation.php) | Asset | Aggregate filter options for supported metadata types (used in asset grid filters) |
| [FileSizeSumAggregation](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Aggregation/Asset/FileSizeSumAggregation.php) | Asset | Sum file sizes across assets for a search. Use `FileSizeAggregationServiceInterface` for a simplified API. |

## Implementation Details

### Wildcard Support

Some modifiers support wildcard characters in search terms:

- `*` matches any character sequence (e.g. `Car*` matches "Car", "Carbon", "Carpet")
- `?` matches exactly one character (e.g. `Car?` matches "Card", "Cars")

### PQL Field Name Resolution

Some modifiers support
[Pimcore Query Language (PQL)](../09_Pimcore_Query_Language/README.md)
field name resolution via `$enablePqlFieldNameResolution` (enabled by default).
This allows using short field names instead of full indexed paths.

## Creating Custom Search Modifiers

Creating a custom search modifier requires two steps:

### 1. Define the Modifier Model

Create a class implementing `ModifierInterface`. This model holds the modifier's
configurable attributes. See
[IdFilter](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Model/Search/Modifier/Filter/Basic/IdFilter.php)
for an example.

### 2. Implement the Handler

Create a service with the
[AsSearchModifierHandler](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Attribute/DefaultSearch/AsSearchModifierHandler.php)
attribute. Apply the attribute to either a method or a class (uses `__invoke`).

The handler method requires exactly two parameters:
- The modifier model (from step 1)
- `SearchModifierContextInterface $context`

See
[BasicFilters](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/SearchIndexAdapter/DefaultSearch/Search/Modifier/Filter/BasicFilters.php)
for an example and the
[Default Search Models documentation](../06_Default_Search_Models/README.md)
for manipulating the search query.
