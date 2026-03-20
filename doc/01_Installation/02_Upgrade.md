---
title: Upgrade Information
description: Version-specific upgrade instructions and breaking changes for the Generic Data Index bundle.
---

# Upgrade Information

## Upgrade to 2026.1.0

- Added support for PHP 8.5.
- Removed support for PHP 8.3 and Symfony v6.

## Upgrade to 2.2.0

- **[Indexing]** Added `id` column as new primary key to `generic_data_index_queue`.
  Run migrations after updating.
- **[Searching]** Added `trackTotalHits` parameter to `DefaultSearchService` and
  `SearchExecutionService`. Default value: `true` (always compute accurate total hits,
  even beyond the search engine's threshold). Set to `null` to use the engine's default
  threshold, or pass an integer for a specific limit.

## Upgrade to 2.1.0

- Added support for Symfony 7
- **[Indexing]** Added sort index for documents
- **[Indexing]** Improved field collection indexing to prevent mapping conflicts when
  properties share a name but differ in type
- Reindex all elements to use the new features:
  ```bash
  bin/console generic-data-index:update:index -r
  ```

## Upgrade to 2.0.0

- **[Indexing]** Added inherited fields indicator to data object indexing
- **[Indexing]** Added functionality to enqueue dependent items
- **[Indexing]** Added class ID field for data object elements
- **[Indexing]** Index name prefix changed from e.g. `pimcore_car` to
  `pimcore_data-object_car`. Old indices are **not deleted** automatically -
  delete them manually if necessary.
- **[Searching]** Added `ClassIdsFilter` modifier to filter data objects by class ID
  or class name
- Added `isElementLocked()` to `ElementLockService` for retrieving element lock status
  from the index
- Reindex all elements to use the new features:
  ```bash
  bin/console generic-data-index:update:index -r
  ```

### Breaking Changes

- Removed deprecated alias `generic-data-index.opensearch-client`.
  Use `generic-data-index.search-client` instead.
- Replaced all deprecated OpenSearch namespace classes with DefaultSearch equivalents:
  - `Pimcore\...\Model\OpenSearch` → `Pimcore\...\Model\DefaultSearch`
  - `Pimcore\...\Enum\SearchIndex\OpenSearch` → `Pimcore\...\Enum\SearchIndex\DefaultSearch`
- Removed deprecated class `Pimcore\...\Exception\OpenSearch\SearchFailedException`.
  Use `Pimcore\...\Exception\DefaultSearch\SearchFailedException` instead.
- Removed deprecated class `AsSearchModifierHandler` from OpenSearch namespace.
  Use `Pimcore\...\Attribute\Search\AsSearchModifierHandler` instead.
- Removed deprecated `AbstractAdapter` classes from OpenSearch namespace.
  Use `Pimcore\...\SearchIndexAdapter\DefaultSearch\...\AbstractAdapter` instead.
- Added default `data-object_` prefix to all data object class definition index names
  to avoid conflicts with other index names.
- Added element type parameter to `getIds` method of `ElementSearchResult`.
- Added `getSpecialPermissions` method to `ElementPermissionServiceInterface`
  for workspace language permissions.
- Removed layout permission from `DataObjectPermissions` (not index-relevant).
- Removed `isLocked` property from index elements (now dynamically calculated).
- Changed workspace permissions evaluation to align with Admin Classic permission system.

#### Interface Changes

- Added `PermissionTypes $permissionType` parameter (default: `PermissionTypes::LIST`) to:
  - `AssetSearchServiceInterface::search`
  - `DocumentSearchServiceInterface::search`
  - `DataObjectSearchServiceInterface::search`
  - `ElementSearchServiceInterface::search`
- Search service `byId` methods now return elements based on `PermissionTypes::VIEW`
- Added type-specific interfaces:
  - `AssetSearch` → `AssetSearchInterface`
  - `DocumentSearch` → `DocumentSearchInterface`
  - `ElementSearch` → `ElementSearchInterface`
- Search services now require type-specific search objects:
  - `AssetSearchServiceInterface::search` requires `AssetSearchInterface`
  - `DocumentSearchServiceInterface::search` requires `DocumentSearchInterface`
  - `ElementSearchServiceInterface::search` requires `ElementSearchInterface`
- `SearchProviderInterface` now returns type-specific search interfaces.

## Upgrade to 1.3.0

- **[Indexing]** Added Elasticsearch support alongside OpenSearch (OpenSearch remains
  the default). To use Elasticsearch, update your Symfony configuration:

```yaml
pimcore_generic_data_index:
  index_service:
    client_params:
      client_name: default
      client_type: 'elasticsearch'
```

- **[Indexing]** Introduced service alias `generic-data-index.search-client`, replacing
  the deprecated `generic-data-index.opensearch-client` (removed in 2.0). The alias
  provides a `Pimcore\SearchClient\SearchClientInterface` instance compatible with both
  OpenSearch and Elasticsearch.
- OpenSearch namespace classes are deprecated. Use DefaultSearch namespace instead.
- Reindex all elements after upgrading or switching search engines:
  ```bash
  bin/console generic-data-index:update:index
  ```

## Upgrade to 1.1.0

- Reindex all elements to use the new features:
  ```bash
  bin/console generic-data-index:update:index
  ```
