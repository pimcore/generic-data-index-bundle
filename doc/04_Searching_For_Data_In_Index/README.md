# Searching For Data In Index

The Generic Data Index bundle adds standardized and flexible services to search data from the search indices.

Each search is based on a search service (depending on the element type) and a search model defining the search. The search models can be created with the [SearchProviderInterface](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Service/Search/SearchService/SearchProviderInterface.php)


## Asset Search Service

### Example usage

- Example: This example loads all assets from the root folder (parent ID 1) and orders them by their full path.
```php
public function searchAction(SearchProviderInterface $searchProvider)
{
    $assetSearch = $searchProvider->createAssetSearch()
                ->addModifier(new ParentIdFilter(1))
                ->addModifier(new OrderByFullPath())
                ->setPageSize(50)
                ->setPage(1);
}
```

## Data Object Search Service

- Example: This example loads all data objects from the root folder (parent ID 1) and orders them by their full path.
```php
public function searchAction(SearchProviderInterface $searchProvider)
{
    $assetSearch = $searchProvider->createDataObjectSearch()
                ->addModifier(new ParentIdFilter(1))
                ->addModifier(new OrderByFullPath())
                ->setPageSize(50)
                ->setPage(1);
}
```

## Search Modifiers

To influence the data which gets fetched its possible to use so-called search modifiers.
Find out details about search modifiers in the [search modifiers documentation](05_Search_Modifiers/README.md).

## OpenSearch Search Models
OpenSearch search models can be used when individual OpenSearch queries are needed to streamline the creation of OpenSearch search JSONs.

Take a look at the dedicated [OpenSearch search models documentation](06_OpenSearch_Search_Models/README.md) to find out more.

## Permissions
The search service respects the user permissions and user workspaces in connection to his roles.

Details about permissions and workspaces can be found in the [permissions and workspaces documentation](08_Permissions_Workspaces/README.md).

## Debug OpenSearch Queries
To debug the OpenSearch queries which are created by the search service, it is possible to use the following magic parameter in the URL (when debug mode is enabled):

| Get Parameter             | Description |  |
| ------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |  |
| debug-open-search-queries | Will change the response of the called URL and outputs all executed OpenSearch queries. It is possible to influence the output verbosity with the value of the parameter (1=normal, 2=verbose, 3=very verbose)          