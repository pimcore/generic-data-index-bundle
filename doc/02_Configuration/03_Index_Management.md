---
title: Index Management
description: Manage search indices for the Generic Data Index, including creation, updates, queue processing, and deployment.
---

# Index Management

The Generic Data Index must index all assets, data objects, and documents
to power search and listing features in Pimcore.

## Console Commands Overview

| Command | Description |
|---------|-------------|
| `generic-data-index:update:index` | Update index mappings and queue all elements for reindex from the database |
| `generic-data-index:update:index -r` | Delete and recreate indices, then queue all elements |
| `generic-data-index:reindex` | Native search engine reindex (reorganizes data within existing indices, no database read) |
| `generic-data-index:deployment:reindex` | Update indices only for class definitions changed since the last deployment |

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
and can safely be deleted, as long as they are not referenced by any alias.

The default index settings use `number_of_shards: 1`, so the total shard count of the
cluster scales with the number of class definitions — not with the suffix scheme.

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

## Index Options

Configure search engine-specific index options. Check your engine's documentation
for available settings.

### Maximum Result Window

The default limit of retrievable items is 10,000. Increase it with:

```yaml
pimcore_generic_data_index:
    index_service:
        index_settings:
            max_result_window: 20000
```

### Total Fields Limit

The default field limit per index is 1,000. Increase it with:

```yaml
pimcore_generic_data_index:
    index_service:
        index_settings:
            mapping.total_fields.limit: 20000
```

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
