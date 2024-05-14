# Use Pimcore Query Language (PQL) as a Developer

If you want to use the Pimcore Query Language (PQL) as a developer to search for data in the Pimcore Generic Data Index, you can use one of the following methods:

#### 1. Search Modifier for the Generic Data Index search services

You can use the [PqlFilter](https://github.com/pimcore/generic-data-index-bundle/blob/1.x/src/Model/Search/Modifier/QueryLanguage/PqlFilter.php) search modifier to filter search results based on a PQL query. The `PqlFilter` search modifier can be used with the search services provided by the Generic Data Index bundle. Take a look at the [Search Services](../README.md) documentation for details.

#### 2. Direct use of the PQL processor to get the search query

Use the `Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ProcessorInterface` together with the `Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexEntityServiceInterface` to process a PQL query.

```php
// inject both services via Symfony dependency injection
/** @var \Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ProcessorInterface $queryLanguageProcessor */
/** @var \Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexEntityServiceInterface $indexEntityService */

$query = $queryLanguageProcessor->process(
    'color = "red" or color = "blue"', // The PQL query
    $indexEntityService->getByEntityName('Car') // 'Asset', 'Document' or the name of the data object class
);

// $query is now a valid OpenSearch query array which can be used to search in the index
```