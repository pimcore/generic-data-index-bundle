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
| `generic-data-index:cleanup:unused-indices` | Delete managed indices not referenced by any alias (`-odd`/`-even` suffixes only) |

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
