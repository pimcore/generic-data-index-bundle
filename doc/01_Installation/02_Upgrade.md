# Upgrade Information

Following steps are necessary during updating to newer versions.

## Upgrade to 2.5.9
- [Commands] `generic-data-index:update:index` now exits with a non-zero status code when one or more of its
  update sections (class definition, asset index, full update) fail, instead of always returning `0`. All sections
  are still attempted and the queue is still dispatched; the command only reports the failure at the end. Deployment
  pipelines that treated a partial failure as success will now fail visibly — this mirrors the same change made for
  `generic-data-index:deployment:reindex` and `generic-data-index:reindex` in 2.5.6.
- [Commands] Added a read-only `generic-data-index:status` command that reports the index queue depth (and whether
  items are still pending dispatch), every live index with its document count and size, and a warning for any index
  present in both the `-even` and `-odd` version at once (the fingerprint of an interrupted reindex).
- [Logging] Generic Data Index now logs to a dedicated `pimcore_generic_data_index` Monolog channel, so its output
  can be filtered, raised to debug, or routed separately. The channel is still handled by the application's existing
  handlers — no configuration change is required.
- [Logging] Failure paths (index checksum read, queue enqueue, dispatch handler) now log with structured context and
  the original exception, a claimed queue batch's dispatch id is logged across dispatch and processing for
  correlation, and the per-class mapping-checksum reindex decision (skip vs. reindex, with stored vs. current
  checksum) is logged.

## Upgrade to 2.5.8
- [Indexing] A failed native reindex no longer triggers a forced recreation of the live index. Recreation now only
  happens when the reindex reports that the existing documents are incompatible with the new mapping (e.g. after a
  field type change); genuine errors — unreachable search cluster, timeouts, rejected requests — propagate and fail
  the operation instead, so a transient connection failure during deployment can no longer purge the index.
- [Indexing] Transient failures of single task-status requests during a long-running reindex are now retried instead
  of aborting the reindex, and an aborted reindex cancels the server-side task before cleaning up its target index.
- [Indexing] `SearchIndexServiceInterface::reindex()` (`@internal`) now returns a `ReindexResult` enum instead of `void`.

## Upgrade to 2.5.6
- [Commands] `generic-data-index:deployment:reindex` and `generic-data-index:reindex` now exit with a non-zero status
  code when reindexing fails, instead of always returning `0`. Deployment pipelines executing these commands will now
  fail visibly on reindex errors — previously such errors were only printed while the process reported success.
- [Indexing] Reindexing failures are no longer silently swallowed: if both the reindex and the fallback index
  recreation fail, the exception now propagates and the mapping checksum is not stored, so the reindex of the class
  definition is retried on the next run.

## Upgrade to 2.5.4
- [Searching] The full-text search (`FullTextSearch` modifier) now defaults to `default_operator: AND` and
  `flags: PHRASE|WHITESPACE` for better relevance and to treat characters like `-` and `.` as literal text.

## Upgrade to 2.5.3
- [Indexing] Added `isReferenced` field to asset index to support filtering for unreferenced assets.
- [Indexing] Fixed: Unpublished data objects are now correctly indexed in relation fields (ManyToOne, ManyToMany, AdvancedManyToMany)

### Re-indexing required
After upgrading, execute the following command to re-index elements to include all modifications:
```
bin/console generic-data-index:update:index -r
```

## Upgrade to 2.2.0
- [Indexing] Added `id` column as new primary key to `generic_data_index_queue`. Please make sure to execute migrations.
- [Searching] Added `trackTotalHits` parameter to `DefaultSearchService` and `SearchExecutionService`. The default value is true, which means that total hits will always be computed accurately, even if they exceed the search engines threshold for accurate hit calculation. Change this parameter to `null`, to use the default threshold, pass an integer value to set a specific one.

## Upgrade to 2.1.0
- Added support for Symfony 7
- [Indexing] Added sort index for documents
- [Indexing] Improved indexing of field collections to prevent mapping conflicts when properties have the same name but different types
- Execute the following command to reindex all elements to be able to use all new features:
  ```bin/console generic-data-index:update:index -r```

## Upgrade to 2.0.0
- [Indexing] Added inherited fields indicator to data object indexing
- [Indexing] Added functionality to enqueue dependent items
- [Indexing] Added class ID field for data object elements
- [Indexing] Added prefix for index names of data objects, these names changed from e.g. `pimcore_car` to `pimcore_data-object_car`. Old indexes are **not deleted** automatically. You need to delete them manually if necessary.
- [Searching] Added new `ClassIdsFilter` modifier to search for data object elements by class ID or class name
- Added a new method `isElementLocked()` to the `ElementLockService`, which provides functionality to retrieve element locked status based on the index data
- Execute the following command to reindex all elements to be able to use all new features:
  ```bin/console generic-data-index:update:index -r```
  
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
- Removed property `isLocked` from Index for elements as it needs to be dynamically calculated
- Changed workspace permissions evaluation in order to align more with the Pimcore Classic bundle permission system

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
