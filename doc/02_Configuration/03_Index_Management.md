---
title: Index Management
description: Manage search indices for the Generic Data Index, including creation, updates, queue processing, and deployment.
---

# Index Management

The Generic Data Index must index all assets, data objects, and documents
to power search and listing features in Pimcore.

For an overview of which changes require a reindex, which of them are handled automatically and which command to run for the rest, see the [Reindexing Guide](../03_Reindexing/README.md).

## Console Commands Overview

| Command | Description |
|---------|-------------|
| `generic-data-index:update:index` | Update index mappings and queue all elements for reindex from the database |
| `generic-data-index:update:index -r` | Delete and recreate indices, then queue all elements |
| `generic-data-index:reindex` | Native search engine reindex (reorganizes data within existing indices, no database read) |
| `generic-data-index:deployment:reindex` | Update indices only for class definitions changed since the last deployment |
| `generic-data-index:cleanup:unused-indices` | Delete managed indices not referenced by any alias (`-odd`/`-even` suffixes only) |
| `generic-data-index:status` | Status of every index (complete, rebuilding with progress, copying, degraded, incomplete), queue depth and failed messages; `--wait` and `--check` for pipelines |

## Index Prefix

Define an index name prefix to avoid naming collisions in shared search engine clusters.
The default prefix is `pimcore_`.

```yaml
pimcore_generic_data_index:
    index_service:
        client_params:
            index_prefix: 'my_prefix'
```

## Created Indices

The Generic Data Index creates the following indices:

- **Assets** - one alias and one index
- **Data objects** - one alias and one index per class definition

Each index uses an alias (e.g. `<prefix>_asset`) pointing to the current index
(e.g. `<prefix>_asset-odd`). The alias name stays constant; the backing index alternates
between `-odd` and `-even` suffixes during reindexing (see
[Updating index structure](#updating-index-structure) below).

### Why `-odd` and `-even` Suffixes?

The alternating suffixes enable zero-downtime reindexing (blue-green approach): when the
index structure changes, the bundle builds a new index under the opposite suffix while
the current index keeps serving reads and writes through the alias. Once the new index
is fully populated, the alias is switched atomically and the old index is deleted.

At rest there is only **one** concrete index — and therefore one set of shards — per
asset index or class definition. Both suffixes exist side by side only transiently
while a reindex is running. If `-odd` and `-even` indices for the same alias exist
permanently, they are leftovers from a reindex that was interrupted (e.g. by a killed
process) before its cleanup could run. Such orphaned indices are not used by the bundle
and can be removed with the [cleanup command](#cleaning-up-unused-indices).

The [default index settings](#index-options) use `number_of_shards: 1`, so the total
shard count of the cluster scales with the number of class definitions — not with the
suffix scheme.

## Keeping Indices Up to Date

Create and update indices with:

```bash
bin/console generic-data-index:update:index
```

This command creates the indices and queues all assets and data objects for indexing.
The Symfony Messenger `pimcore_generic_data_index_queue` transport processes the queue.

### Index Refresh

By default, the index refreshes after each bulk operation since items are processed
asynchronously via Symfony Messenger.

To force synchronous processing (immediate refresh), inject
`SynchronousProcessingServiceInterface` and call `enable()`:

| Method | Description |
|--------|-------------|
| `enable()` | Enable synchronous processing |
| `disable()` | Disable synchronous processing |
| `isEnabled()` | Check current mode |

### Queue Options

Configure the indexing queue batch behavior:

| Option | Default | Description |
|--------|---------|-------------|
| `worker_count` | 1 | Number of parallel `messenger:consume` workers. Improves batch size calculation. |
| `min_batch_size` | 5 | Minimum items per batch (relevant with multiple workers) |
| `max_batch_size` | 400 | Maximum items per batch |

The queue calculates batch sizes dynamically between `min_batch_size` and `max_batch_size`
based on the number of workers and queue depth.

```yaml
pimcore_generic_data_index:
    index_service:
        queue_settings:
            worker_count: 1
            min_batch_size: 5
            max_batch_size: 400
```

### Related Elements

Updating an element automatically enqueues its related elements for reindexing.
By default, this runs asynchronously through Symfony Messenger.

For immediate processing, use `SynchronousProcessingRelatedIdsServiceInterface`:

| Method | Description |
|--------|-------------|
| `enable()` | Activate synchronous processing |
| `disable()` | Revert to asynchronous processing |
| `isEnabled()` | Return current processing mode |

:::info

`SynchronousProcessingRelatedIdsServiceInterface` does not affect delete operations.
Deletes always process synchronously.

:::

### Repairing Indices

To delete and recreate an index from the Pimcore database (e.g. after an incompatible
mapping change), pass the `-r` option:

```bash
bin/console generic-data-index:update:index -r
```

Without `-r`, the command only updates the index mapping and queues all items for reindex.

### Updating Index Structure

Index mappings update automatically when system languages or class definition fields change.
For manual updates, run the reindex command. This performs a native OpenSearch/Elasticsearch
reindex within the search indices (no database read):

```bash
bin/console generic-data-index:reindex
```

#### Reindex Options

Native reindexing is submitted as an asynchronous task to OpenSearch/Elasticsearch and its progress is polled until
the task has finished. Two options control how long the process waits:

- **max_polls** (default 720): maximum number of polling attempts before the reindex is aborted
- **poll_interval** (default 5): seconds to wait between two status polls

With the defaults, a reindex may therefore run for up to one hour (720 × 5 seconds). If reindexing an index takes
longer than this budget, the command aborts with an error and a non-zero exit code — increase `max_polls` for very
large indices:

```yaml
pimcore_generic_data_index:
    index_service:
        reindex_settings:
            max_polls: 720
            poll_interval: 5
```

Transient failures while polling (e.g. a temporarily unreachable or overloaded search cluster) are retried and do not
abort a running reindex. If reindexing fails for a different reason — the cluster is unreachable, the task reports an
error or times out server-side — the command fails instead of modifying the live index, and the reindex is retried on
the next run. The index is only recreated (and repopulated via the index queue) when the already indexed documents are
incompatible with the new mapping, for example after a field type change.

### Handling Failed Messages

The messenger retries failed messages 3 times, then routes them to the
`pimcore_generic_data_index_failed` transport. Retry failed messages with:

```bash
bin/console messenger:failed:retry -vv
```

See the [Symfony Messenger documentation](https://symfony.com/doc/current/messenger.html#saving-retrying-failed-messages)
for additional commands.

## Index Status

An index doesn't show whether it is complete. After a mapping change the index is copied or recreated and then
refilled from the database by the messenger workers; until they are done, filters, saved searches, exports and
reports see incomplete data. The index status tells, per index, what state it is in and how far a rebuild has got, for
PHP code, on the command line and as an event.

### States

| State | Meaning | Progress |
| --- | --- | --- |
| `complete` | All elements are indexed. Queue entries still waiting are the normal lag of asynchronous indexing and are reported separately. | - |
| `rebuilding` | A full rebuild from the database was queued (after a class change, `update:index`, a recreated index) and its entries are not all processed yet. | elements already processed / elements; 0 while the elements are still being queued |
| `degraded` | Documents still carry the state of a copy that left out fields; the rebuild rewrites them. Only occurs after a copy that left out fields. | documents rewritten / documents |
| `copying` | An odd/even copy of the index is running in the search engine; search shows the old index until the alias switches. | - |
| `incomplete` | Documents are missing and nothing is queued: run `update:index` for the index. | - |
| `missing` | The index doesn't exist. | - |

Each status also carries the counts it is derived from: documents in the index, elements in the database, waiting
queue entries, documents without checksum, whether a copy runs, and whether the mapping and the index settings are
current (`null` when no state is stored yet, see [Deployment and Index Management](#deployment-and-index-management)).
The measures are read live; nothing is cached.

A rebuild counts as queued from a full enqueue of the index (class save with a mapping change, `update:index`,
`deployment:reindex` with a change, a recreated index): the mark is set before the first element is queued (workers
draining the queue between two chunks of a large enqueue don't end the rebuild) and removed by exactly one process,
the messenger worker that processes the last batch, or the enqueue itself when the workers were faster or the index
has no elements. A mark left behind by a worker that died after its last batch doesn't hide missing documents: with
an empty queue the counts decide, so such an index shows `incomplete` and asks for a rebuild. If the process
queuing the elements was terminated between two chunks, the mark keeps its queueing phase: the index shows
`rebuilding` at 0 % with `rebuildEnqueuing` and the time it was queued (the command prints
`rebuilding, queueing since …`); run the full enqueue again (`update:index`, `deployment:reindex`), which replaces
the mark. A rebuild queued by a version before this feature isn't marked, so the index shows `complete` with
waiting entries, or `incomplete` once the queue is empty.

### Command Line

```bash
bin/console generic-data-index:status                      # overview: queue, indices, status table
bin/console generic-data-index:status --index=Car          # one index: alias name, class name, asset, document
bin/console generic-data-index:status --json               # machine-readable
bin/console generic-data-index:status --check              # exit code 1 while an index is not complete
bin/console generic-data-index:status --wait --timeout=7200 --index=Car   # block until complete, exit code 1 on timeout
```

`--wait` and `--check` are meant for deployment pipelines: run the step that needs the index only after
`generic-data-index:status --wait` has returned. `--json` leaves stdout to the report; errors, the failure of
`--check` and the progress of `--wait -v` go to stderr. When the status cannot be read (search engine or database
unavailable, the tasks API failing, a count or task list the engine answered only partially because of failed
shards or nodes) the command exits with 1 instead of guessing, so an unknown state never passes as complete.

### PHP

```php
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexStatusServiceInterface;

public function __construct(private IndexStatusServiceInterface $indexStatusService)
{
}

public function export(ClassDefinition $classDefinition): void
{
    $status = $this->indexStatusService->getStatusForClassDefinition($classDefinition);
    if (!$status->isComplete()) {
        // $status->getState(), $status->getPercentage(), $status->getPendingEntries()
        throw new RuntimeException(sprintf('Index %s is %s', $status->getIndexName(), $status->getState()->value));
    }
    // ...
}
```

`getAssetStatus()`, `getDocumentStatus()`, `getDataObjectFolderStatus()` and `getStatusByIndexName()` read the other
indices; `getAll()` returns a report with every index plus the total queue depth and the number of failed messages;
`waitUntilComplete($indexName, $timeoutSeconds)` polls until the index is complete and throws an
`IndexStatusException` on timeout.

### Event

`Pimcore\Bundle\GenericDataIndexBundle\Event\IndexStatus\IndexCompleteEvent` fires once when the last queue
entries of a queued full rebuild have been processed and the index is complete again. It is dispatched by the
messenger worker that processes that batch, so listeners run in the worker; when the workers were faster than the
enqueue, or the index has no elements, the process that queued the rebuild dispatches it. Two workers finishing
their last batches at once dispatch one event, not two. If the search engine can't be reached right after the last
batch, the check is repeated with the next batch of that index.

```php
use Pimcore\Bundle\GenericDataIndexBundle\Event\IndexStatus\IndexCompleteEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class StartExportWhenIndexComplete
{
    public function __invoke(IndexCompleteEvent $event): void
    {
        $event->getIndexName();           // e.g. "pimcore_data-object_car"
        $event->getStatus()->getLabel();  // e.g. "class Car (CAR)"
    }
}
```

## Index Options

Configure search engine-specific index options. Check your engine's documentation
for available settings.

### Maximum Result Window

The bundle sets the limit of retrievable items to 10,000,000 by default
(`config/pimcore/default_index_settings.yaml`). Change it with:

```yaml
pimcore_generic_data_index:
    index_service:
        index_settings:
            max_result_window: 20000000
```

### Total Fields Limit

The bundle sets the field limit per index to 100,000 by default
(`config/pimcore/default_index_settings.yaml`). Classification stores are a common reason to exceed it:
each group is mapped with each of its keys (and, for a localized store, each language). Restricting the
allowed groups of the classification store field in the class definition maps only those groups and
reduces the field count. Otherwise, increase the limit with:

```yaml
pimcore_generic_data_index:
    index_service:
        index_settings:
            index:
                mapping:
                    total_fields:
                        limit: 200000
```

Use the nested keys shown above. Your `index_settings` are merged key by key into the defaults,
so only the same nested path replaces the default value.

:::info

If the index already exists, recreate it after changing this setting:

```bash
bin/console generic-data-index:update:index -r
```

:::

## Deployment and Index Management

### Class Definition Changes

After updating class definitions during deployment, run:

```bash
bin/console generic-data-index:deployment:reindex
```

This updates the index structure for all class definitions modified since the last
deployment and reindexes data objects for affected classes.

### Cleaning Up Unused Indices

To clean up managed indices that are no longer referenced by any alias, run:

```bash
bin/console generic-data-index:cleanup:unused-indices
```

This only targets indices with the configured prefix and a `-odd` or `-even` suffix.
To preview deletions without making changes, use `--dry-run`:

```bash
bin/console generic-data-index:cleanup:unused-indices --dry-run
```

During a reindex the new `-odd`/`-even` index is created and populated before it is attached
to its alias, so for the duration of that window it carries the configured prefix and suffix
but is not referenced by any alias. To avoid deleting an index that is actively being built,
indices younger than `--min-age` seconds (default: `86400`, i.e. 24 hours) are never
considered unused. Indices whose creation date cannot be determined are also skipped.

```bash
bin/console generic-data-index:cleanup:unused-indices --min-age=3600
```

> **Warning:** Only lower `--min-age` or disable the guard entirely (`--min-age=0`) when you
> are sure no reindex (e.g. `generic-data-index:reindex` or
> `generic-data-index:deployment:reindex`) is currently running or expected to take longer
> than the chosen threshold. Run a `--dry-run` first to review what would be deleted.

### Other Index Changes

With index options the command also checks the data object folder, asset and document indices, and compares
`index_settings` and data versions as well. This covers e.g. predefined asset metadata, valid languages from
config files, `system_fields_settings`, `search_analyzer_attributes` and `ExtractMappingEvent` fields.

```
php bin/console generic-data-index:deployment:reindex --all
```

| Option | Checked indices |
| --- | --- |
| `--all` | All of the below |
| `--classes` / `--folders` / `--assets` / `--documents` | Only the named indices |
| `--dry-run` | Prints the planned action per index, changes nothing |

| Detected change | Action |
| --- | --- |
| Mapping | Native reindex, then rebuild from the database |
| Index settings | Native reindex |
| Data version | Rebuild from the database |
| Index missing | Create the index, then rebuild from the database |

The values are stored per index after a successful update; a failed index is retried on the next run. The first
run with index options only stores the current values as the baseline. If that deployment also changes a
mapping, run `generic-data-index:update:index` once; for `index_settings`, run `generic-data-index:reindex` once
(it does not cover the data object folder index).

A native reindex switches the alias to a copy of the index; index updates processed during the copy are lost.
Run the command while the index queue workers are stopped.

Code changes that alter indexed values without changing the mapping (calculators, `UpdateIndexDataEvent`
subscribers, custom adapters) are not detected: raise the matching version in the same change. In
`query_store` mode a rebuild reads the stored calculator values, see
[Refreshing calculated values after a calculator change](#refreshing-calculated-values-after-a-calculator-change).

```yaml
pimcore_generic_data_index:
    index_versions:
        assets: 1        # also: documents, folders
        classes:
            CAR: 3       # class definition ID
```

## Calculated Fields Index Mode

By default, values of calculated value fields are computed **live** while index data is
extracted: the field's calculator class is executed for every calculated field of every element on
every (re)indexing — for localized calculated fields once per language. For expensive calculators
(relation loads, external services) this can dominate the indexing cost on large installations.

With the `query_store` mode, the value stored in the object's **query table** (written on every
save — the same value SQL-based grid listings use) is indexed instead, and the calculator is never
executed during indexing:

```yaml
pimcore_generic_data_index:
    index_service:
        calculated_fields_index_mode: 'query_store' # default: 'live'
```

Notes:

- The indexed value is the **save-time snapshot** as a string, truncated to the field
  definition's `columnLength` (default 190). The fields stay searchable, filterable and sortable.
- Changed calculator logic is only reflected after an element is saved again (see
  [Refreshing calculated values after a calculator change](#refreshing-calculated-values-after-a-calculator-change) below).
- Calculated fields inside field collections and object bricks are not read from the query store
  and keep live behavior.
- Switching the mode does not change the index mapping, so it does not trigger an automatic
  reindex: values converge as elements are saved or reprocessed by the index queue.

### Refreshing calculated values after a calculator change

In `query_store` mode the indexed value is the **save-time snapshot** stored in the object's query
table (`object_query_*`), which is (re)written only when an object is **saved**. Changing a
calculator's code therefore does not update already-stored values on its own — exactly as with
Pimcore's classic SQL grids and listings, which read the same query-table snapshot.

To refresh calculated values after a calculator change, **re-save the affected objects** (e.g. a
short console command that loads the class's objects in chunks and calls `->save()` on each). A save
recomputes the calculated fields, rewrites `object_query_*`, and enqueues the element for reindexing,
so both the query table and the index pick up the new logic. For large object counts, batch the
re-save and disable versioning for the run to reduce cost.

> A dedicated command to recompute the query-table snapshots without a full object save is planned
> as a follow-up.
