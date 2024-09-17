# Search Modifiers

Search modifiers can influence the search results by modifying the search query. They can be used to filter, sort or aggregate the search results. 

Search modifiers can be added to the search via the `addModifier()` method of the search object.

```php
$search->addModifier(new ParentIdFilter(1))
```

## Available Search Modifiers

### Filters

| Modifier                                                                                                                                               | Modifier Category         | Description                                                                                                                                                                                                                                                                                                            |
|--------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| [IdFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Basic/IdFilter.php)                          | Basic filters             | Filter by element ID                                                                                                                                                                                                                                                                                                   |
| [IdsFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Basic/IdsFilter.php)                        | Basic filters             | Filter by multiple element IDs                                                                                                                                                                                                                                                                                         |
| [ExcludeFoldersFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Basic/ExcludeFoldersFilter.php)  | Basic filters             | Exclude folders from search result                                                                                                                                                                                                                                                                                     |
| [ParentIdsFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Tree/ParentIdsFilter.php)             | Tree related filters      | Filter by parent ID                                                                                                                                                                                                                                                                                                    |
| [PathFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Tree/PathFilter.php)                       | Tree related filters      | Filter by path (depending on use case for all levels or direct children only and with or without the parent item included)                                                                                                                                                                                             |
| [TagFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Tree/TagFilter.php)                         | Tree related filters      | Filter by tag IDs (it is also possible to include child tags)                                                                                                                                                                                                                                                          |
| [AssetMetaDataFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Asset/AssetMetaDataFilter.php)    | Asset filters             | Filter by asset meta data attribute. The format of the `$data` which needs to be passed depends on the type of the meta data attribute and is handled by its [field definition adapter](https://github.com/pimcore/generic-data-index-bundle/tree/1.x/src/SearchIndexAdapter/OpenSearch/Asset/FieldDefinitionAdapter). |
| [WorkspaceQuery](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Workspaces/WorkspaceQuery.php)         | Workspace related filters | Filter based on the user workspaces and permissions for a defined element type (this query is added to the asset/document/data object search by default)                                                                                                                                                               |
| [ElementWorkspacesQuery](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Workspaces/WorkspaceQuery.php) | Workspace related filters | Filter based on the user workspaces and permissions respecting all element types (this query is added to the element search by default)                                                                                                                                                                                |
| [MultiSelectFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/FieldType/MultiSelectFilter.php)    | Field type filters        | Filter text fields by a list of exact strings. Supports [PQL field name resolution](#pql-field-name-resolution).                                                                                                                                                                                                                                                                         |
| [DateFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/FieldType/DateFilter.php)           | Field type filters        | Filter date fields based on an exact date or a range of dates. Supports [PQL field name resolution](#pql-field-name-resolution).                                                                                                                                                                                       |



### Full Text Search Queries

| Modifier                                                                                                                                        | Modifier Category | Description                                                                                                                                              |
|-------------------------------------------------------------------------------------------------------------------------------------------------|-------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------|
| [ElementKeySearch](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/FullTextSearch/ElementKeySearch.php) | Full text search  | Search by element key like in the studio UI with [wildcard support](#wildcard-support).                                                                  |
| [WildcardSearch](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/FullTextSearch/WildcardSearch.php)       | Full text search        | Filter text fields based on search terms with [wildcard support](#wildcard-support) and [PQL field name resolution support](#pql-field-name-resolution). |


### Dependencies

| Modifier                                                                                                                                                   | Modifier Category | Description                                             |
|------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------|---------------------------------------------------------|
| [RequiresFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Dependency/RequiresFilter.php)             | Dependencies      | Get all elements which the given element requires.      |
| [RequiredByFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Dependency/RequiredByFilter.php)         | Dependencies      | Get all elements which are required by the given element. |
| [NoDependenciesFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Dependency/NoDependenciesFilter.php) | Dependencies      | Get all elements which have no dependencies.      |


### Query Language

| Modifier                                                                                                                         | Modifier Category | Description                                                                               |
|----------------------------------------------------------------------------------------------------------------------------------|-------------------|-------------------------------------------------------------------------------------------|
| [PqlFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/QueryLanguage/PqlFilter.php) | Query Language    | Apply a [Pimcore Query Language (PQL)](../09_Pimcore_Query_Language/README.md) condition. |

### Sort Modifiers

If multiple sort modifiers are added to the search, the order of the modifiers is important. The search result will be sorted by the first added modifier first, then by the second added modifier and so on.

| Modifier                                                                                                                                     | Modifier Category      | Description                                                                                                                                                                                                                                                                                                                                                                         |
|----------------------------------------------------------------------------------------------------------------------------------------------|------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| [OrderByFullPath](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Sort/Tree/OrderByFullPath.php)     | Tree related sorting   | Order by full path (including element key)                                                                                                                                                                                                                                                                                                                                          |
| [OrderByField](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Sort/OrderByField.php)                | Field based sorting    | Order by given field name.<br/>If `$enablePqlFieldNameResolution` is set to true (default) [Pimcore Query Language](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Sort/OrderByField.php) field name resolution logic is enabled. Therefore it's possible to use short field names then instead of specifying the full path in OpenSearch. |
| [OrderByPageNumber](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Sort/Tree/OrderByPageNumber.php) | Search related sorting | Use inverted search for large amounts of data (this modifier is added to the search when there are at least 1000 results by default, and page number is above the half of total pages. Furthermore, existing sorting has to be already applied.)                                                                                                                                    |
| [OrderByIndexField](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Sort/Tree/OrderByIndexField.php) | Search related sorting | Order by object tree index for custom tree sorting. This modifier is currently applied only for data objects!                                                                                                                                                                                                                                                                       |

### Aggregations


| Modifier                                                                                                                                                           | Modifier Category        | Description                                                                                                                                                                                         |
|--------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| [ChildrenCountAggregation](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Aggregation/Tree/ChildrenCountAggregation.php)  | Tree related aggregation | Get children counts for given element IDs.                                                                                                                                                          |
| [AssetMetaDataAggregation](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Aggregation/Asset/AssetMetaDataAggregation.php) | Assets                   | Used for the filters in the asset grid to aggregate the filter options for supported meta data types.                                                                                               |
| [FileSizeSumAggregation](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Aggregation/Asset/FileSizeSumAggregation.php) | Assets                   | Aggregates the sum of file sizes for all assets for a given search. The `FileSizeAggregationServiceInterface` internally uses this aggregation and provides an easy way to use this functionality. |

## Search Modifier Implementation Details

### Wildcard support

For some search modifiers, wildcard support is available. Wildcards support the following characters:
- `*` can be used to match any sequence of characters, regardless of length - for example "Car*" to find all items starting with "Car".
- `?` can be used to match exactly one character - for example "Car?" to find all items starting with "Car" and having one more character.

### PQL field name resolution

Some modifiers support [Pimcore Query Language (PQL)](../09_Pimcore_Query_Language/README.md) field name resolution by setting `$enablePqlFieldNameResolution` to `true` (enabled by default). Therefore, it's possible to use short field names then instead of specifying the full path in OpenSearch. 

## Add your own search modifier

To add a custom search modifier implementation two steps are necessary:

1. Create a new class that implements the `Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\ModifierInterface` interface. 
This model class should contain all configurable attributes for the modifier. Take a look at the [IdFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Basic/IdFilter.php) for an example.

2. Create a service to implement the logic behind the modifier and add the [AsSearchModifierHandler](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Attribute/OpenSearch/AsSearchModifierHandler.php) attribute. 
The attribute can either be directly added to the method which implements to logic or to a class. If added to a class the ´__invoke` method will be used as the handler.

The implemented method needs exactly two arguments.:
* First argument: the modifier model (see step 1).
* Second argument: [SearchModifierContextInterface](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/OpenSearch/Modifier/SearchModifierContextInterface.php) $context

Take a look at the [BasicFilters](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/SearchIndexAdapter/OpenSearch/Search/Modifier/Filter/BasicFilters.php) for an example and the [OpenSearch search models documentation](../06_OpenSearch_Search_Models/README.md) for more details about the search models to manipulate the search.