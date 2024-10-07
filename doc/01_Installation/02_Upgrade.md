# Upgrade Information

Following steps are necessary during updating to newer versions.

## Upgrade to 1.1.0
- Execute the following command to reindex all elements to be able to use all new features:

  ```bin/console generic-data-index:update:index```

## Upgrade to 2.0.0

### BC-Breaks

#### Interface changes
-  Added `PermissionTypes $permissionType` parameter with default type `PermissionTypes::LIST` to
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