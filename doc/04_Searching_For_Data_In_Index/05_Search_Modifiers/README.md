# Search Modifiers

Search modifiers can influence the search results by modifying the search query. They can be used to filter, sort or aggregate the search results. 

Search modifiers can be added to the search via the `addModifier()` method of the search object.

```php
$search->addModifier(new ParentIdFilter(1))
```

## Available Search Modifiers

### Filters

| Modifier                                                                              | Modifier Category    | Description                    |
|---------------------------------------------------------------------------------------|----------------------|--------------------------------|
| [IdFilter](../../../src/Model/Search/Modifier/Filter/Basic/IdFilter.php)              | Basic filters        | Filter by element ID           |
| [IdsFilter](../../../src/Model/Search/Modifier/Filter/Basic/IdsFilter.php)            | Basic filters        | Filter by multiple element IDs |
| [ParentIdsFilter](../../../src/Model/Search/Modifier/Filter/Tree/ParentIdsFilter.php) | Tree related filters | Filter by parent ID            |

### Full Text Search Queries

| Modifier                                                                                   | Modifier Category | Description                                                                                                                                           |
|--------------------------------------------------------------------------------------------|-------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------|
| [ElementKeySearch](../../../src/Model/Search/Modifier/FullTextSearch/ElementKeySearch.php) | Full text search  | Search by element key like in the studio UI.<br/><br/>* can be used for wildcard searches - for example "Car*" to find all items starting with "Car". |

### Sort Modifiers

| Modifier                                                                            | Modifier Category    | Description                                |
|-------------------------------------------------------------------------------------|----------------------|--------------------------------------------|
| [OrderByFullPath](../../../src/Model/Search/Modifier/Sort/Tree/OrderByFullPath.php) | Tree related sorting | Order by full path (including element key) |

### Aggregations


| Modifier                                                                                                     | Modifier Category        | Description                                |
|--------------------------------------------------------------------------------------------------------------|--------------------------|--------------------------------------------|
| [ChildrenCountAggregation](../../../src/Model/Search/Modifier/Aggregation/Tree/ChildrenCountAggregation.php) | Tree related aggregation | Get children counts for given element IDs. |

## Add your own search modifier

To add a custom search modifier implementation two steps are necessary:

1. Create a new class that implements the `Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\ModifierInterface` interface. 
This model class should contain all configurable attributes for the modifier. Take a look at the [IdFilter](../../../src/Model/Search/Modifier/Filter/Basic/IdFilter.php) for an example.

2. Create a service to implement the logic behind the modifier and add the [AsSearchModifierHandler](../../../src/Attribute/OpenSearch/AsSearchModifierHandler.php) attribute. 
The attribute can either be directly added to the method which implements to logic or to a class. If added to a class the ´__invoke` method will be used as the handler.

The implemented method needs exactly two arguments.:
* First argument: the modifier model (see step 1).
* Second argument: [SearchModifierContextInterface](../../../src/Model/OpenSearch/Modifier/SearchModifierContextInterface.php) $context

Take a look at the [BasicFilters](../../../src/SearchIndexAdapter/OpenSearch/Search/Modifier/BasicFilters.php) for an example.