---
title: Upgrade Information
description: Version-specific upgrade instructions and breaking changes for the Generic Data Index bundle.
---

# Upgrade Information

## Upgrade to 2026.4.0

### Requires Pimcore 2026.4

- [Diagnostics] The bundle now requires `pimcore/pimcore` `^2026.4`, because its diagnostic checks implement the core
  diagnostics contract (`Pimcore\Contracts\Diagnostics`). Generic Data Index 2026.4 is released together with
  Pimcore 2026.4; upgrade both together. See [Diagnostics](../06_Diagnostics/README.md).

### Re-indexing recommended

- [Indexing] Fixed: localized fields inside object bricks, field collections and blocks were normalized with the
  class-level localized field definitions instead of the container's. Their values were indexed unconverted
  (relations as empty objects), or converted with the type of a class-level localized field of the same name. Their
  mapping (`localizedfields.<language>.<field>`) did not match the documents (`localizedfields.<field>.<language>`),
  so the values were only mapped dynamically, and filtering or sorting a localized object brick column in the
  Studio grid found nothing or failed.
- [Indexing] They are now mapped and indexed like the class-level localized fields, without the `localizedfields`
  level: `<container path>.<field>.<language>`, for example `bricks.SaleInformation.title.en`. This is the key the
  Studio grid filters and sorts object brick columns by. Values of data types without a Generic Data Index adapter
  are no longer indexed there.
- [Indexing] In `query_store` mode, calculated values inside object bricks, field collections and blocks are always
  calculated live. Before, one with the same name as a class-level localized calculated value got the class-level
  value.
- [Indexing] The mapping of classes with localized fields inside object bricks, field collections or blocks changes.
  Re-indexing is recommended, not required: nothing that worked before breaks without it. Without it, the Studio grid
  opens and its filters and sorting keep working, but localized object brick fields stay as they were: elements not
  saved since the update are not found by filters on them, and sorting by them fails until the first element is saved
  with the new version. Run `generic-data-index:deployment:reindex` after the update to fix this; it re-indexes only
  the classes whose mapping changed. Saving such a class in the class editor has the same effect.
  `generic-data-index:update:index -c <class id> -r` builds the index from scratch; `deployment:reindex` copies the
  existing documents into the new index first, so the old `localizedfields` fields stay in its mapping (with values
  only until the elements are re-indexed).
- [Indexing] Without the re-index, elements saved with the new version are indexed at the new paths and are found and
  sorted, but these paths are mapped dynamically: the first value decides the type. A decimal in a field first
  indexed with a whole number is indexed as a whole number, and a text in a field first indexed with a date-like
  value is rejected, so the element is not updated in the index (logged as `mapper_parsing_exception`).
- [Indexing] The old path `<container path>.localizedfields.<field>.<language>` (for example
  `bricks.SaleInformation.localizedfields.title.en`) is no longer indexed. The documents of these elements change
  when they are re-indexed or saved again: the `localizedfields` level disappears and the values move next to the
  container's other fields. Update PQL queries, saved filters and `UpdateIndexDataEvent` / `ExtractMappingEvent`
  listeners that use the old path. `generic-data-index:status` shows the affected indices as mapping not current
  until the re-index. The index format of class-level localized fields is unchanged.
- [Indexing] Snapshots exported before the update are refused for these classes, and `--force` skips them: export
  them again after the re-index.
- [Indexing] Fixed: the mapping of a localized classification store declared only the `default` node under each
  group, while the documents are written as `<field>.<group>.<language>.<key>` for every valid language. The
  language nodes were therefore only mapped dynamically. The mapping now declares one node per entry of the same
  language set the documents use — `default` plus every valid language — the `default` node carrying every indexed
  key of the group and a language node the localized ones.
- [Indexing] The classification store's nested root is now declared with `dynamic: false`. This is not cosmetic: the
  root ran on the engine default `dynamic: true`, so a key an editor filled in was auto-mapped from its first indexed
  value, and that guess has the wrong shape. A text key (input, textarea, wysiwyg, select, multiselect, country, …) is
  declared as `text` with a `keyword` sub-field that accepts values up to 1024 characters and a `sort` sub-field;
  dynamic mapping gives it `text` with a `keyword` sub-field capped at 256 characters and no `sort` at all. Sorting by
  such a key therefore does not work, and a value longer than 256 characters is silently missing from filters on it.
  With `dynamic: false` a group, language or key that is not declared is kept in `_source`, is not indexed, and does
  not fail the bulk request, so once the index carries only the declared mapping, every indexed path has the shape its
  key definition asks for. Declaring the key levels is about that shape, not about avoiding an index recreation: for a
  text key the declared mapping is applied over a guessed one in place, and where it is not — see the last note of
  this section — the fallback is a native copy, not a recreation.
- [Indexing] `dynamic: false` only keeps undeclared nodes from being **added** to the mapping; it does not remove what
  dynamic mapping put there before. An in-place mapping update (`PUT _mapping`, what `update:index` tries first) merges
  the new declaration into the existing mapping and leaves those group, language and key nodes in place, so on an
  index that is upgraded in place they keep being indexed and stay filterable. Only a mapping built from scratch drops
  them: a native reindex into the other odd/even index, or `update:index -r`. A group, language or key that dynamic
  mapping had already created therefore keeps matching filters until that rebuild, and the declared field count of the
  merged mapping is the sum of both.
- [Indexing] The user-visible consequence: a newly configured classification store key is **not filterable until the
  next mapping update**. Four paths rebuild the mapping *and* re-queue every object of the class, so values entered in
  the meantime become filterable without a manual re-index and nothing is lost: saving the class definition in the
  class editor, `generic-data-index:deployment:reindex`, `generic-data-index:update:index`, and a change of the valid
  languages. They all end up enqueuing the whole `object_<classId>` table.
- [Indexing] `generic-data-index:reindex` is the exception to the re-queue, not to the filtering: it forces a native
  copy of every index and queues nothing. The copy is created with the new mapping and the documents are copied into
  it, so a value that sat unindexed in `_source` under the old mapping is indexed through the new one and becomes
  filterable — no database rebuild is needed for it. What the copy cannot bring in is what is *not* in `_source`: an
  element that was never indexed, a change saved in the database since the last index write, or a document the engine
  rejected. Those need a rebuild from the database, so run `generic-data-index:update:index` instead of it, or after
  it, to rebuild the mapping and enqueue the objects in one step. And if the copy falls back to recreating an index —
  the existing values don't fit the new mapping — that index is left empty and nothing refills it, because the command
  queues nothing; see [Known Limitations](../03_Reindexing/README.md#known-limitations).
- [Indexing] Only the groups allowed by the class field are mapped (unchanged), so under `dynamic: false` the values
  of a group that is active on an object but not among the field's allowed groups are not filterable — on an index
  that already mapped that group dynamically, from the first mapping built from scratch (see above), not from the
  in-place update. They are still written to the document and stay readable in `_source`; only filters, PQL and grid
  columns on them stop matching. Add the group to the field's allowed groups and re-index to restore filtering.
- [Indexing] New: a classification store key whose own definition carries the JSON boolean `"indexed": false` is no
  longer mapped and no longer written to the document, so it is not filterable, sortable or searchable — in every group
  the key is assigned to. Only that boolean on a definition that reads as a JSON object excludes it: a definition
  without the entry, an empty, unparsable or null definition, one that reads as a scalar, and any other value of the
  entry all mean indexed, and the `indexed` entry of a key–group relation's metadata is not read. On an existing index
  the two halves arrive separately: the value leaves `_source` only when an
  object's document is rewritten, so it needs the re-queue that saving the class definition,
  `generic-data-index:deployment:reindex` or `generic-data-index:update:index` performs — `generic-data-index:reindex`
  copies the documents as they are and keeps it (see above) — while the node an earlier mapping already declared for the
  key is dropped when that index is built from scratch, also as above.
- [Indexing] The declared field count of a classification store multiplies by the number of valid languages for its
  localized keys only: every indexed key is declared once under `default`, and a localized key once more per valid
  language. A large store can therefore hit `index.mapping.total_fields.limit` when the index is created or the
  mapping is updated, instead of later at write time. Restrict the allowed groups of the field, mark the keys that do
  not need a language as not localized, or raise the limit — see
  [Total Fields Limit](../02_Configuration/03_Index_Management.md#total-fields-limit).
- [Indexing] A class whose only language-dependent data is a classification store with at least one localized key now
  changes its checksum when the valid languages change, because those languages are part of its mapping.
  `deployment:reindex` picks such a class up by itself, where it used to skip it and the class had to be raised in
  `index_versions.classes` or reindexed with `update:index -c <id>`. A language change can therefore reindex more
  classes than before. A store whose keys are all non-localized does not depend on the language list at all, so its
  mapping and its checksum do not change and `deployment:reindex` skips it.
- [Indexing] Changed: a classification store key is mapped and written under the languages the key itself is kept in —
  `default`, plus every valid language for a key that is localized — instead of under every valid language of the
  field. For a store at ETIM proportions (76,625 key assignments, 17 valid languages, 23,222 of the assignments
  localized) the field count the field declares drops from 1,379,250 to 471,399. Flipping a key's localization or
  index flag changes the class mapping but fires no event this bundle listens to, so apply it with
  `generic-data-index:deployment:reindex` or `generic-data-index:update:index`; add `-r` (or let the command copy the
  index natively) to drop the language nodes an earlier mapping declared, and note that the language values already
  written stay in `_source` until each object's document is rewritten.
- [Indexing] Behavior change on the read side: `ClassificationStoreFilter` takes the language node from its `$locale`
  argument and does not resolve it from the key, so a filter that passes a language for a key that is **not** localized
  now finds nothing — that key is only indexed under `default`, which is the argument's default. The wrong node is an
  empty result, not an error, so it reads like an absent value. The locale is per key, not per group: a group holding
  one localized and one non-localized key needs the language for the first and `default` for the second.
  `Service\SearchIndex\ClassificationStore\KeyLocaleServiceInterface::getLocaleForKey()` answers the node of a key id
  with the resolution the mapping and the document use themselves, for callers that do not already know it.
- [Indexing] Behavior change: classification store groups and keys are indexed under their identifiers instead of
  their names, so renaming one no longer moves an indexed path and two same-named keys no longer collide. Run
  `pimcore:classificationstore:backfill-identifiers` and then `generic-data-index:deployment:reindex` (one reindex,
  mandatory); an entity still without an identifier stays indexed under its name. Update PQL queries, saved
  filters, `UpdateIndexDataEvent` / `ExtractMappingEvent` listeners and `ClassificationStoreFilter` callers: its
  `$group` argument and the field name of its sub-modifier are now identifiers, and the name of an entity that has an
  identifier returns an empty result.
- [Indexing] A class mapping update that removes a class field (a variant, key or group, or a field of the class
  definition) now copies the index into the other `-odd`/`-even` index, because an in-place update would leave the
  removed field mapped; an additive change is applied in place, whatever undeclared fields the live index has picked
  up. Asset, document and folder indices are unchanged.
- [Indexing] Changed: a classification store key is now indexed exactly as a class field of its type is, and the
  type is no longer silently skipped when the indexer does not recognise it — it is reported as an error naming the
  store, group, key and type, and that key is not indexed. One type is exempt: an `encryptedField` key is still
  skipped without the error, because an encrypted value is deliberately not indexed. A `multiValue` key publishes a
  JSON array in its own field, with each value at its stored position and a position holding none as `null` up to the last
  position that holds a value (a key holding one value at position 0 publishes a one-element array), and adds no field. A declared variant publishes a sibling field
  named `<key>_<variant>`, filterable by passing that name to `ClassificationStoreFilter` like any key, and
  multiplies the key's declared fields by one plus the number of variants per language node it is declared in. On a
  store whose engine cannot hold what a key declares (`variants` or `multiValue: true`), that key declares and
  publishes nothing — neither its siblings nor its own field — and is reported once per key as a warning. A key of a relation type declares the fields its class-field
  counterpart does: three per language node for a relation, and for an advanced relation eight plus one per
  configured column. Declaring or removing a variant, or changing the store's engine, changes the class mapping, so apply it
  with `generic-data-index:deployment:reindex` or `generic-data-index:update:index`.
- [Indexing] Existing indices already contain language and key nodes that dynamic mapping created with a guessed type,
  and the update declares the real types over them. For a text key this is accepted in place: the type stays `text`,
  the declared sub-fields are added to the guessed one, and `ignore_above` is updatable. A numeric key can conflict: a
  key whose first indexed value happened to be a whole number was guessed as `long`, while a non-integer numeric key
  is declared as `double`, and that change is rejected in place. The bundle then copies the index natively into its
  odd/even twin and swaps the alias — expensive on a large index, but not destructive, because the existing values
  parse into the declared types. Apply the new mapping with `bin/console generic-data-index:update:index`; add `-r` to
  build the affected indices from scratch instead, which is also what removes the nodes dynamic mapping created.

### Workflow places: run `update:index` right after the deployment

- [Indexing] Places of workflows using the `state_table` marking store are indexed in the new system field
  `workflowPlaces` (keyword per workflow name), see
  [Workflow Places](../04_Searching_For_Data_In_Index/README.md#workflow-places).
  Run `bin/console generic-data-index:update:index` to update the mapping and fill the field for existing elements.
  Run it right after the deployment: elements indexed before the mapping is updated get a dynamic `text` mapping for
  the new field, and the later update to `keyword` then needs a full re-index.
  The `workflowPlaces` mapping depends on the configured `state_table` workflows. It is not part of the class mapping
  checksum, so `generic-data-index:deployment:reindex` does not reindex every class because of it: run
  `generic-data-index:update:index` after adding or removing a `state_table` workflow.
- [Indexing] Snapshots exported before the update are still accepted, but importing one writes its documents as they
  were exported, without `workflowPlaces`. Export snapshots again after the update, or run
  `generic-data-index:update:index` again after importing an older snapshot.
- [Indexing] `SettingsStoreServiceInterface` has two new methods, `getWorkflowPlacesMappingChecksum()` and
  `storeWorkflowPlacesMappingChecksum()`: custom implementations must add them.

## Upgrade to 2026.3.5
- [Messenger] A copy that runs out of its budget (`reindex_settings.max_polls` × `poll_interval`) is no longer
  retried with the same budget: the class mapping and language messages report it as unrecoverable, so the message
  goes to the failed transport right away. Raise `max_polls`, then retry it with `messenger:failed:retry`. The
  timeout is a `ReindexTimeoutException`, a subclass of `ReindexFailedException`, which is no longer final.
- [Messenger] A class mapping message that is handled twice (redelivered after `redeliver_timeout`, or a second save
  of the class within the copy time) no longer copies the index twice: the second handling waits for the lock of the
  index and skips the copy when the first one stored the checksum meanwhile. `ClassDefinitionReindexService` takes
  the `IndexLockInterface` and the `SearchIndexConfigServiceInterface` as additional constructor arguments.
- [Messenger] A failed language update (`UpdateLanguageSettingsMessage`) is no longer only logged as
  `Updating languages failed`: the exception reaches Messenger, so the message is retried and ends in
  `pimcore_generic_data_index_failed`, where it can be retried once the cause is fixed. A retried message of a
  settings save that a later save has replaced is skipped, so it cannot reinstall the language list of its own
  save: the languages of the latest save are recorded in the settings store (`LatestLanguageChangeInterface`).
  Language updates never overlap: the whole update runs under a lock shared by all language messages, which the
  updates of the single indices inside it renew, so a second one waits, then checks for a later save.
  `IndexLockInterface::refresh()` renews every lock the process holds, and a nested `run()` renews the locks
  already held once it has the new one. During a rolling deployment, a language change saved on a node that still
  runs the previous version is not recorded; if a node on the new version recorded a change before, the workers
  skip the older node's message (notice `Skipping a language change that a later settings save has replaced`).
  Change the languages after the deployment, or run `deployment:reindex --all` afterwards.
  `UpdateClassMappingHandler` and `UpdateLanguageSettingsHandler` take a `MappingUpdateRetryPolicyInterface` as a
  further constructor argument, the language handler and `AdminSettingsSubscriber` also the
  `LatestLanguageChangeInterface`.
- [Indexing] Mapping updates of the same index no longer overlap: every copy or recreation of an index runs under a
  lock on the index alias, and a second update of the same index waits until the first one has finished. The lock is
  taken from the lock store of the application (`Symfony\Component\Lock\LockFactory`; Pimcore configures the
  database store, so all workers and nodes share it) and expires, if its process dies, after the longest copy attempt
  (the wait of `reindex_settings.max_polls` × `poll_interval` plus the cancellation of a stuck task before and after
  it) and a quarter of that. `AbstractIndexHandler` and the three index handlers take an `IndexLockInterface` as
  their last constructor argument; constructing them without it is deprecated and leaves the mapping updates of
  that handler unlocked.
- [Indexing] Fixed: deleting a class definition no longer leaves the delete queue entries of its objects behind.
  They were dispatched again every 24 hours and failed each time with `index_not_found_exception`, blocking the
  other elements of their batch. The entries are now removed together with the class index, and a bulk deletion
  from an index that no longer exists counts as done instead of failing the batch. Messages that already failed
  for this reason succeed on `messenger:failed:retry`.
- [Commands] `generic-data-index:deployment:reindex` without options no longer stops at the first class whose index
  update fails: it continues with the remaining classes, reports every failed class and exits with a non-zero status,
  like a run with index options.
- [Indexing] Two concurrent mapping updates of the same index (for example two class saves handled by two messenger
  workers) could delete the live index: both copied into the same target, the first to finish switched the alias to
  it, and the failing second one cleaned its target up. A copy now never deletes an index the alias points to. When
  it finds the alias on its target, it logs that another copy finished first and applies its mapping with a second
  copy from that index ([#641](https://github.com/pimcore/platform-version/issues/641)).
- [Indexing] New option `index_service.reindex_settings.mapping_change_strategy` (`recreate`, the default, or `copy`)
  decides what happens to the documents of an index when its mapping changes. The default keeps the behaviour of
  previous versions: the documents are copied as they are, and the index is recreated empty when their old values
  don't fit the new mapping (for example after a field type change). One thing is new in that case: a rebuild of the
  recreated index from the database is always queued, also by `generic-data-index:reindex` and by a settings-only
  `deployment:reindex`, so the index fills again without a manual `update:index`.
  With `copy`, the odd/even copy leaves out the changed fields whose old values don't fit the new type and keeps the
  others (for example a number copied into a text field). The index is only recreated as a last resort, when the copy
  fails and no further field can be left out (after at most 3 attempts). System fields are never left out: if their
  old values don't fit, the index is recreated. The documents stay searchable; the left-out fields are empty, so
  filters and sorting on them find nothing, until the messenger workers have rebuilt all objects of the class from the
  database. The copy blocks the messenger worker that handles the class mapping message for about 1.5 ms per document
  (measured: 100,000 documents of about 6 KB in about 2.5 minutes on a development machine with OpenSearch 2); the
  rebuild runs at the usual indexing rate. See the [Reindexing Guide](../03_Reindexing/README.md#field-type-changes).
- [Indexing] Upgrading from a version before 2026.2.8: the numeric mapping change of 2026.2.8 (`float` to `double`, or
  `long` for integer fields) is applied by the odd/even copy as well. Both keep their values, so the indices stay
  complete. Typed `CalculatedValue` fields of 2026.2.9 (text to a number, boolean or date) are left out of the copy
  and filled again by the rebuild.
- [Commands] Behavior change: `generic-data-index:reindex` now adds all elements of an index to the index queue if its
  copy left out fields, the index had to be created empty, or a custom index handler applied the mapping, and prints a
  notice per index. Before, it never filled the index queue. Operators who relied on that need messenger workers running
  after the command.
- [Indexing] A mapping update that changed an index but failed before its rebuild was queued (for example the
  database became unavailable after the copy) leaves a mark in the settings store; the next class save,
  `deployment:reindex` or `generic-data-index:reindex` of that index queues the rebuild, although its own copy
  changes nothing.
- [Commands] `generic-data-index:deployment:reindex` and its `--dry-run` list per index the changed fields: with
  `copy` the fields stripped, the fields kept and the fields outside class and custom fields that are copied as they
  are; with `recreate` the changed fields copied as they are, and which of them `copy` would leave out.
- [Indexing] New methods on `@internal` interfaces, relevant only for custom implementations of them:
  `IndexHandlerInterface::reindexMappingWithResult()` and `getMappingDiff()`,
  `SearchIndexServiceInterface::reindexWithoutFields()`, `ReindexServiceInterface::reindexAllIndicesWithReport()`,
  `ClassDefinitionReindexServiceInterface::reindexClassDefinitionMapping()`.
- [Deprecation] `AbstractIndexHandler` has a new optional constructor argument `IndexCopyServiceInterface`. Subclasses
  that call the parent constructor without it keep working and trigger a deprecation; the argument will be required in
  the next major version. `reindexMapping()` keeps its signature. A subclass that overrides it is still called by the
  bundle; because the bundle cannot tell what the override did, it then queues a rebuild of the index from the database.
  Project code that calls `reindexMapping()` directly gets the copy with stripped fields too, but nothing is queued:
  after a field type change, queue the elements of the index (for example with
  `generic-data-index:update:index -c <class id>`), or the stripped fields stay empty.

## Upgrade to 2026.3.4
- [Searching] New optional setting `search_settings.full_text_search_fields`: the fields a full-text search runs
  against when it does not name fields itself. Use it on indices with many fields, where the expansion over all fields
  makes searches slow or exceeds the field expansion limit. Without it nothing changes. See
  [Full-text search fields](../02_Configuration/03_Index_Management.md#full-text-search-fields).
- [Searching] Search and count requests where some shards failed are logged as a warning on the
  `pimcore_generic_data_index` log channel. The response itself is unchanged.

## Upgrade to 2026.3.3
- [Commands] `generic-data-index:deployment:reindex` has new opt-in options (`--all`, `--assets`, `--dry-run`, …) that
  also cover the folder, asset and document indices and `index_settings`. Without options nothing changes. See
  [Deployment and Index Management](../02_Configuration/03_Index_Management.md#deployment-and-index-management).
- [Indexing] A native reindex now fails if the new index cannot be created (e.g. invalid `index_settings`) instead of
  switching to an index with dynamic mapping.
- [Indexing] New **index status**: `IndexStatusServiceInterface` reports per index whether it is complete, rebuilding
  (with the progress in percent), copying, degraded, incomplete or missing, with the counts it is derived from;
  `IndexCompleteEvent` fires in the messenger worker that processes the last batch of a queued full rebuild. See
  [Index Status](../02_Configuration/03_Index_Management.md#index-status).
- [Commands] `generic-data-index:status` shows the status of every index and the number of failed messages, and
  has new options: `--index`, `--json`, `--check` (exit code 1 while an index is not complete) and `--wait` (block
  until complete). Its leftover warning no longer lists an index whose odd/even copy is running. Without options the
  output and the exit code are unchanged.
- [Indexing] A full rebuild queued by `EnqueueServiceInterface` (class save with a mapping change, `update:index`,
  `deployment:reindex`) is now marked in the settings store until its last batch is processed; the mark is what tells a
  rebuild from the normal lag of asynchronous indexing.
- [Indexing] The progress of a running odd/even copy is logged every minute (documents copied so far).
- [Database] A new migration adds an index on `generic_data_index_queue.elementIndexName` (the status counts the
  entries per index). Please make sure to execute migrations.
- [Indexing] New methods on `@internal` interfaces, relevant only for custom implementations of them:
  `SearchIndexServiceInterface::countDocuments()` and `getReindexTasks()`, `SettingsStoreServiceInterface::removeIndexState()`,
  `replaceIndexState()` and `removeIndexStateIfEquals()`, `IndexStateServiceInterface::isMappingCurrent()`.

## Upgrade to 2026.3.2
- [Permissions] Searches that check a permission other than `list` (for example `byId()`, which checks `view`) no
  longer return the parent elements of a user's workspaces. As in Pimcore core, only `list` includes them, so the
  tree can show the way down to an allowed element. Requires `pimcore/studio-backend-bundle` 2026.3.2 or later:
  older versions load the folder of grids and the advanced search with `byId()` and return `404` for users who may
  only list it, for example the root folder.
- [Permissions] Fixed: a workspace now only applies to its own path and the paths below it. Before, the permission
  check also applied it to any path that merely contained the workspace path (for example `/Products` to
  `/Archive/Products/2024`), and the search query treated a workspace such as `/Products2` as lying below
  `/Products`. Effective permissions and search results for elements below such paths change accordingly.

## Upgrade to 2026.3.0

- [Searching] Added the `SearchBodyProcessorInterface` extension point (tag
  `pimcore.generic_data_index.search_body_processor`, autoconfigured) to transform the serialized
  search body for every search and count request.
- [Elasticsearch] Raised the minimum supported Elasticsearch server version to `8.12.2`
  (`pimcore/elasticsearch-client` `^2026.3` now requires the `elasticsearch/elasticsearch` client library `^8.12`).
- Added command `generic-data-index:cleanup:unused-indices` to delete managed indices not referenced by any alias.
- [Searching] Element search result items now expose the relevance score via the new
  `ScoreAwareResultItemInterface`.
- [Searching] The search result models now expose `getMaxScore()`

## Upgrade to 2026.2.10
- [Commands] Added `generic-data-index:snapshot:export` and `generic-data-index:snapshot:import`. Export writes every
  search index into a portable, gzipped JSON-lines bundle (plus a manifest) in a configurable Flysystem storage, so a
  bundle can be moved between installations without a full reindex. Import recreates the local indices from the
  bundle and replays the documents through the bulk API; it never enqueues elements, and refuses the import (unless
  `--force` is given) when a class's mapping checksum in the manifest does not match the local class definition.
  Snapshots are not rotated: they accumulate in the storage until an operator deletes them. See
  [Index snapshots](../02_Configuration/06_Index_Snapshots.md) for the commands and configuration.
- [Configuration] Added the `pimcore_generic_data_index.snapshot` node (`storage`, `page_size`, `page_bytes`,
  `bulk_size`, `bulk_bytes`, `import_workers` — the import sends bulk requests through 4 worker processes by default)
  and a default private Flysystem storage `pimcore.generic_data_index_snapshot.storage` under
  `var/generic-data-index/snapshots`.
- [Bug] `generic-data-index:status` (and any caller of `IndexStatsService::getStats()`) no longer fails with
  "Undefined array key aggregations" when no search index exists yet.

## Upgrade to 2026.2.9

### Re-indexing required

- [Indexing] `CalculatedValue` fields are no longer always mapped as text/keyword: fields configured with
  element type `boolean` are now mapped as `boolean`, `numeric` as `double` and `date` as `date`
  (`strict_date_time_no_millis`). The text-based element types (`input`, `textarea`, `html` — including the
  default) keep the existing text/keyword mapping. This makes term filters, aggregations and the Studio grid
  filters work on typed calculated fields.
- The mapping change is applied by `bin/console generic-data-index:update:index`: the search engine rejects the
  in-place mapping update, so the command copies the affected data object indices (odd/even copy; see the
  [Reindexing Guide](../03_Reindexing/README.md) for when an index is recreated instead) and queues all elements for
  re-indexing. On large indices, see the
  [Operations Guide](../02_Configuration/07_Operations_Guide.md#bundle-upgrades-that-change-mappings).

## Upgrade to 2026.2.8

### Re-indexing required

- [Indexing] `Numeric`, `NumericRange`, `QuantityValue` and `Slider` fields are no longer mapped as 32-bit `float`.
  Fields configured as integer are now mapped as `long`, all other ones (including `Slider`) as `double`. This prevents
  exact-match filters on large integers (for example customer or account IDs above 2^24) from returning
  neighbouring values that shared the same rounded `float` representation.
- The mapping change is applied by `bin/console generic-data-index:update:index`: the search engine rejects the
  in-place mapping update, so the command copies the affected data object indices (odd/even copy; see the
  [Reindexing Guide](../03_Reindexing/README.md) for when an index is recreated instead) and queues all elements for
  re-indexing. On large indices, see the
  [Operations Guide](../02_Configuration/07_Operations_Guide.md#bundle-upgrades-that-change-mappings).

## Upgrade to 2026.1.0

### PHP and Dependency Requirements

- Added support for `PHP` `8.5`.
- Removed support for `PHP` `8.3` and Symfony `v6`.

### Removed ExtJS / Admin Classic

- `PimcoreGenericDataIndexBundle` no longer implements `PimcoreBundleAdminClassicInterface`.
- Removed `BundleAdminClassicTrait`.

### Interface Changes

- Added optional `bool $forceReload = false` parameter to the `byId()` method on the following interfaces:
  - `AssetSearchServiceInterface::byId(int $id, ?User $user = null, bool $forceReload = false)`
  - `DataObjectSearchServiceInterface::byId(int $id, ?User $user = null, bool $forceReload = false)`
  - `DocumentSearchServiceInterface::byId(int $id, ?User $user = null, bool $forceReload = false)`
  - `ElementSearchServiceInterface::byId(ElementType $elementType, int $id, ?User $user = null, bool $forceReload = false)`
- [Indexing] **BC Break**: `IndexQueueRepository::generateSelectQuery()` signature changed. The method now accepts an associative `$columnAliases` array (`alias => expression`) instead of the previous positional `$fields` array with a separate `$idField` parameter. Passing a numerically-indexed array will throw an `InvalidArgumentException`. All enqueue methods in `EnqueueService` and the element type adapters (`AssetTypeAdapter`, `DocumentTypeAdapter`, `DataObjectTypeAdapter`) have been updated accordingly. The `quoteParameters()` helper method has been removed.

### Installer / Messenger Transport Changes

- The bundle installer now implements `PostInstallCommandsProviderInterface`. The post-install command
  `generic-data-index:update:index -r` is automatically executed during `pimcore:install` when using
  Install Profiles. Manual execution after `pimcore:bundle:install` is still required.
- The messenger transport DSN is now configurable via the `%pimcore.messenger.transport_dsn_prefix%`
  container parameter (env: `PIMCORE_MESSENGER_TRANSPORT_DSN_PREFIX`) instead of being hardcoded to
  `doctrine://default`.

## Upgrade to 2.5.9
- [Indexing] Moving or renaming an element with children now rewrites the children's `path`/`fullPath` in the search
  index for **all** element types: data objects are rewritten across all per-class indexes (previously only the
  folder index was updated, so concrete objects kept their old path after a folder move), and documents are
  rewritten for any parent document type (previously only moves of document *folders* were handled, so children of
  a moved/renamed page kept their old path). Stale index paths also affected path-based workspace permission
  filtering of search results.
- [Configuration] **BC break:** the undocumented `max_synchronous_children_rename_limit` setting
  (`pimcore_generic_data_index.index_service.search_settings`) was removed, together with
  `SearchIndexConfigServiceInterface::getMaxSynchronousChildrenRenameLimit()` (`@internal`). Above that limit the
  children path rewrite was silently skipped and never performed by any asynchronous process, leaving the index
  permanently stale — the rewrite now always runs. Remove the setting from your configuration if you had set it;
  Symfony will otherwise fail with an "Unrecognized option" error on container build.
- [Commands] `generic-data-index:update:index` now exits with a non-zero status code when one or more of its
  update sections (class definition, asset index, full update) fail, instead of always returning `0`. All sections
  are still attempted and the queue is still dispatched; the command only reports the failure at the end. Deployment
  pipelines that treated a partial failure as success will now fail visibly — this mirrors the same change made for
  `generic-data-index:deployment:reindex` and `generic-data-index:reindex` in 2.5.6.
- [Commands] Added a read-only `generic-data-index:status` command that reports the index queue depth (and whether
  items are still pending dispatch), every live index with its document count and size, and a warning for any index
  present in both the `-even` and `-odd` version at once (the fingerprint of an interrupted reindex).
- [Logging] Generic Data Index now logs to a dedicated `pimcore_generic_data_index` Monolog channel, so its output
  can be filtered, raised to debug, or routed separately. Handlers without a channel restriction pick it up
  automatically; if you restrict a handler to an explicit channel allow-list (e.g. `channels: ["pimcore"]`), add
  `pimcore_generic_data_index` to that list so its records are not dropped.
- [Logging] Failure paths (index checksum read, queue enqueue, dispatch handler) now log with structured context and
  the original exception, a claimed queue batch's dispatch id is logged across dispatch and processing for
  correlation, and the per-class mapping-checksum reindex decision (skip vs. reindex, with stored vs. current
  checksum) is logged.

## Upgrade to 2.5.8
- [Indexing] The class mapping checksum is now calculated independently of the array key order, so that a changed
  order of e.g. the configured system languages no longer marks unchanged class definitions as changed and triggers
  a native reindex. Checksums stored by earlier versions are recognised and updated automatically, so upgrading by
  itself does **not** reindex existing class definitions. Only if the mapping actually changes in the same
  deployment as the upgrade — including a changed key order, since the previous checksum still depends on it — are
  the affected class definitions reindexed once, as they would have been before. From then on, order-only changes
  no longer trigger a reindex.
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
