---
title: Searching For Data In Index
description: Search assets, data objects, and documents using the Generic Data Index search services.
---

# Searching For Data In Index

The Generic Data Index bundle provides standardized search services for querying
assets, data objects, and documents from the search indices.

Each search uses a type-specific search service and a search model that defines the query.
Create search models with the
[SearchProviderInterface](https://github.com/pimcore/generic-data-index-bundle/blob/2.0/src/Service/Search/SearchService/SearchProviderInterface.php).

## Asset Search

Load all assets from the root folder (parent ID 1), ordered by full path:

```php
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\ParentIdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;

public function searchAction(
    SearchProviderInterface $searchProvider,
    AssetSearchServiceInterface $assetSearchService
) {
    $assetSearch = $searchProvider->createAssetSearch()
                ->addModifier(new ParentIdFilter(1))
                ->addModifier(new OrderByFullPath())
                ->setPageSize(50)
                ->setPage(1);

   $searchResult = $assetSearchService->search($assetSearch);
}
```

## Data Object Search

Load all data objects from the root folder for a specific class definition,
ordered by full path:

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\ParentIdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Model\DataObject\ClassDefinition;

public function searchAction(
    SearchProviderInterface $searchProvider,
    DataObjectSearchServiceInterface $dataObjectSearchService
) {
    $carClassDefinition = ClassDefinition::getByName('Car');

    $dataObjectSearch = $searchProvider->createDataObjectSearch()
                ->addModifier(new ParentIdFilter(1))
                ->addModifier(new OrderByFullPath())
                ->setClassDefinition($carClassDefinition)
                ->setPageSize(50)
                ->setPage(1);

    $searchResult = $dataObjectSearchService->search($dataObjectSearch);
}
```

## Document Search

Load all documents from the root folder, ordered by full path:

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\ParentIdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Document\DocumentSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;

public function searchAction(
    SearchProviderInterface $searchProvider,
    DocumentSearchServiceInterface $documentSearchService
) {
    $documentSearch = $searchProvider->createDocumentSearch()
                ->addModifier(new ParentIdFilter(1))
                ->addModifier(new OrderByFullPath())
                ->setPageSize(50)
                ->setPage(1);

    $searchResult = $documentSearchService->search($documentSearch);
}
```

## Element Search (Cross-Type)

The element search queries assets, data objects, and documents simultaneously.

:::info

The element search does not compute `hasChildren` attributes. `hasChildren` always
returns `false` for all elements in element search results.

:::

Load all elements required by asset ID 123, ordered by full path:

```php
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Dependency\RequiredByFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Element\ElementSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;

public function searchAction(
    SearchProviderInterface $searchProvider,
    ElementSearchServiceInterface $elementSearchService
) {
    $elementSearch = $searchProvider->createElementSearch()
                ->addModifier(new RequiredByFilter(123, ElementType::ASSET))
                ->addModifier(new OrderByFullPath())
                ->setPageSize(50)
                ->setPage(1);

    $searchResult = $elementSearchService->search($elementSearch);
}
```

## Search Modifiers

Search modifiers filter, sort, and aggregate search results. See the
[Search Modifiers documentation](05_Search_Modifiers/README.md) for the full reference
and instructions on creating custom modifiers.

## Retrieving IDs Only

Use `SearchResultIdListServiceInterface` to retrieve search results as ID lists
instead of full objects:

```php
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\ParentIdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchResultIdListServiceInterface;

public function searchAction(
    SearchProviderInterface $searchProvider,
    SearchResultIdListServiceInterface $searchResultIdListService
) {
    $dataObjectSearch = $searchProvider->createDataObjectSearch()
                ->addModifier(new ParentIdFilter(1))
                ->addModifier(new OrderByFullPath())
                ->setPageSize(50)
                ->setPage(1);

    // All IDs for the full search result (ignoring pagination)
    $allIds = $searchResultIdListService->getAllIds($dataObjectSearch);

    // IDs for the current page only
    $idsOnPage = $searchResultIdListService->getIdsForCurrentPage($dataObjectSearch);
}
```

## Default Search Models

To build custom OpenSearch/Elasticsearch queries beyond what the search services offer,
use default search models. The search services
use these models internally.

See the [Default Search Models documentation](06_Default_Search_Models/README.md).

## Permissions

The search services respect user permissions and workspace configurations.

See [Permissions and Workspaces](08_Permissions_Workspaces/README.md).

## Pimcore Query Language (PQL)

[PQL](./09_Pimcore_Query_Language/README.md) defines a query syntax
for searching data objects, assets, and documents.

## Debug Search Queries

When debug mode is enabled, add the `debug-search-queries` GET parameter to any URL
to inspect the executed search queries:

| Value | Output verbosity |
|-------|-----------------|
| `1` | Normal |
| `2` | Verbose |
| `3` | Very verbose |

The response is replaced with the search query output.
