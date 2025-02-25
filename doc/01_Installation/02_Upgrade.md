# Upgrade Information

Following steps are necessary during updating to newer versions.

## Upgrade to 2.0.0
- [Indexing] Added inherited fields indicator to data object indexing
- [Indexing] Added functionality to enqueue dependent items

### BC-Breaks
- Removed deprecated alias `generic-data-index.opensearch-client` and replaced it with `generic-data-index.search-client`
- Removed all deprecated classes from OpenSearch namespaces and replaced them with DefaultSearch namespace instead.
  - `Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch` -> `Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch`
  - `Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch` -> `Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch`
- Removed deprecated class `Pimcore\Bundle\GenericDataIndexBundle\Exception\OpenSearch\SearchFailedException` please use `Pimcore\Bundle\GenericDataIndexBundle\Exception\OpenSearch\SearchFailedException` instead
- Removed deprecated class `Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler` please use `Pimcore\Bundle\GenericDataIndexBundle\Attribute\Search\AsSearchModifierHandler` instead
- Removed deprecated class `Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Asset\FieldDefinitionAdapter\AbstractAdapter` please use `Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Asset\FieldDefinitionAdapter\AbstractAdapter` instead
- Removed deprecated class `Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\DataObject\FieldDefinitionAdapter\AbstractAdapter` please use `Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter\AbstractAdapter` instead
- Added default prefix `data-object_` prefix to all data object class definition index names. This change is necessary to avoid conflicts with other index names.
- Add element type to the `getIds` method of `Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Element\SearchResult\ElementSearchResult`
- Added `getSpecialPermissions` method to `Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\ElementPermissionServiceInterface` to get special permissions workspace language permissions for elements
- Removed layout permission from `Pimcore\Bundle\GenericDataIndexBundle\Permission\DataObjectPermissions` as they are not index relevant

#### Interface changes
- Added `PermissionTypes $permissionType` parameter with default type `PermissionTypes::LIST` to
- `AssetSearchServiceInterface::search` method
- `DocumentSearchServiceInterface::search` method
- `DataObjectSearchServiceInterface::search` method
- `ElementSearchServiceInterface::search` method
- Search services `byId` methods now return elements based on the `PermissionTypes::VIEW` permission
- Added type specific interfaces for searches to avoid mixing up different search types in search services
  - `AssetSearch` now implements `AssetSearchInterface`
  - `DocumentSearch` now implements `DocumentSearchInterface`
  - `ElementSearch` now implements `ElementSearchInterface`
- Search services now require the specific search type for the search
  - `AssetSearchServiceInterface::search` now requires a `AssetSearchInterface`
  - `DocumentSearchServiceInterface::search`  now requires a `DocumentSearchInterface`
  - `ElementSearchServiceInterface::search`  now requires a `ElementSearchInterface`
- `SearchProviderInterface` now returns type specific search interfaces

## Upgrade to 1.4.0
- [Indexing] Added class ID field for data object elements
- [Searching] Added new `ClassIdsFilter` modifier to search for data object elements by class ID or class name
- Execute the following command to reindex all elements to be able to use all new features:

  ```bin/console generic-data-index:update:index```

## Upgrade to 1.3.0
- [Indexing] Added support for Elasticsearch in parallel to Opensearch. Opensearch remains the default search technology. If you are using Elasticsearch, you need to update your symfony configuration as follows:
```yml 
pimcore_generic_data_index:
  index_service:
    client_params:
      client_name: default
      client_type: 'elasticsearch'
```
- [Indexing] Introduced new service alias `generic-data-index.search-client`. This will replace deprecated alias `generic-data-index.opensearch-client` which will be removed in the next major version. 
The new service alias can be used to inject the search client into your services. This search client is an instance of `Pimcore\SearchClient\SearchClientInterface` which is a common interface for OpenSearch and Elasticsearch clients.
- Classes under OpenSearch namespaces are now deprecated and will be removed in the next major version. Please use the classes under the DefaultSearch namespace instead.
- Execute the following command to reindex all elements to be able to use all new features or when switching between OpenSearch and Elasticsearch:

  ```bin/console generic-data-index:update:index```

## Upgrade to 1.1.0
- Execute the following command to reindex all elements to be able to use all new features:

  ```bin/console generic-data-index:update:index```