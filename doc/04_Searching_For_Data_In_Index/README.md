# Searching For Data In Index

Generic Data Index bundle adds standardized and flexible services to search data from the search indices.

## Asset Search Service

Assets can be searched with the [AssetSearchServiceInterface](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Service/Search/SearchService/Asset/AssetSearchServiceInterface.php).

### Example usage

```php
$assetSearch = (new AssetSearch())
    ->addModifier(new ParentIdFilter(1))
    ->addModifier(new OrderByFullPath())
    ->setPageSize(50)
    ->setPage(1)
;
```

This example loads all assets from the root folder (parent ID 1) and orders them by their full path. 

### Search Modifiers

To influence the data which gets fetched its possible to use so-called search modifiers.
Find out details about search modifiers in the [search modifiers documentation](05_Search_Modifiers/README.md).