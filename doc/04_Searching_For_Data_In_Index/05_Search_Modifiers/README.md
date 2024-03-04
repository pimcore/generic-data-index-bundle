# Search Modifiers

Search modifiers can influence the search results by modifying the search query. They can be used to filter, sort or aggregate the search results. 

Search modifiers can be added to the search via the `addModifier()` method of the search object.

```php
$search->addModifier(new ParentIdFilter(1))
```

## Available Search Modifiers

### Filters

| Modifier                                                                                                                                              | Modifier Category         | Description                                                                                                                |
|-------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------|----------------------------------------------------------------------------------------------------------------------------|
| [IdFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Basic/IdFilter.php)                         | Basic filters             | Filter by element ID                                                                                                       |
| [IdsFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Basic/IdsFilter.php)                       | Basic filters             | Filter by multiple element IDs                                                                                             |
| [ExcludeFoldersFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Basic/ExcludeFoldersFilter.php) | Basic filters             | Exclude folders from search result                                                                                         |
| [ParentIdsFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Tree/ParentIdsFilter.php)            | Tree related filters      | Filter by parent ID                                                                                                        |
| [PathFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Tree/PathFilter.php)                      | Tree related filters      | Filter by path (depending on use case for all levels or direct children only and with or without the parent item included) |
| [WorkspaceFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Worksapces/WorkspaceQuery.php)       | Workspace related filters | Filter based on the user workspaces and permissions (this query added to the search by default)                            |



### Full Text Search Queries

| Modifier                                                                                   | Modifier Category | Description                                                                                                                                           |
|--------------------------------------------------------------------------------------------|-------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------|
| [ElementKeySearch](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/FullTextSearch/ElementKeySearch.php) | Full text search  | Search by element key like in the studio UI.<br/><br/>* can be used for wildcard searches - for example "Car*" to find all items starting with "Car". |

### Sort Modifiers

| Modifier                                                                            | Modifier Category    | Description                                |
|-------------------------------------------------------------------------------------|----------------------|--------------------------------------------|
| [OrderByFullPath](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Sort/Tree/OrderByFullPath.php) | Tree related sorting | Order by full path (including element key) |

### Aggregations


| Modifier                                                                                                     | Modifier Category        | Description                                |
|--------------------------------------------------------------------------------------------------------------|--------------------------|--------------------------------------------|
| [ChildrenCountAggregation](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Aggregation/Tree/ChildrenCountAggregation.php) | Tree related aggregation | Get children counts for given element IDs. |

## Add your own search modifier

To add a custom search modifier implementation two steps are necessary:

1. Create a new class that implements the `Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\ModifierInterface` interface. 
This model class should contain all configurable attributes for the modifier. Take a look at the [IdFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/Filter/Basic/IdFilter.php) for an example.

2. Create a service to implement the logic behind the modifier and add the [AsSearchModifierHandler](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Attribute/OpenSearch/AsSearchModifierHandler.php) attribute. 
The attribute can either be directly added to the method which implements to logic or to a class. If added to a class the ´__invoke` method will be used as the handler.

The implemented method needs exactly two arguments.:
* First argument: the modifier model (see step 1).
* Second argument: [SearchModifierContextInterface](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/OpenSearch/Modifier/SearchModifierContextInterface.php) $context

Take a look at the [BasicFilters](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/SearchIndexAdapter/OpenSearch/Search/Modifier/BasicFilters.php) for an example and the [OpenSearch search models documentation](../06_OpenSearch_Search_Models/README.md) for more details about the search models to manipulate the search.