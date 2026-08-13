# Index Management

It is important to index all assets and data object in Pimcore in order to be able to use the search and listing features powered by the Generic Data Index bundle.

## Define Index Prefix

To avoid duplicate names or index interferences in your search engine, it is necessary to define an index name prefix, which is added to all indices created by Generic Data Index. 
Default one is `pimcore_`.

This can be done by defining following configuration:

```yaml 
pimcore_generic_data_index:
    index_service:
        client_params:
            index_prefix: 'my_prefix' # Prefix for all index names created by Generic Data Index
```

## Created Indices

The Generic Data Index generates indices for the following entities:

* Assets search index (one alias and one index)
* Data objects search index (one alias and one index per class definition)

For the asset and data object indices the Generic Data Index uses an alias (e.g. `<index_prefix>_asset`) that points to the
most current index (e.g. `<index_prefix>_asset-odd`). The alias name always stays the same, the index names alternate
between `-odd` and `-even` suffix. For more details also see 'Updating index structure for data indices' in the next section.

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

The element search indices need to be created via the following console commands:

```
# create/update all indices + their mappings and add all items to the index queue
bin/console generic-data-index:update:index
```

The command will create the indices and add all assets and data objects to the index queue. The queue will be processed by Symfony messenger workers (`pimcore_generic_data_index_queue` queue).

### Refreshing of the index

By default, the index queue is refreshed after each bulk operation as the items are processed asynchronously with the Symfony messenger.
If you want to perform index refresh immediately you can use enable synchronous processing by injecting the `SynchronousProcessingServiceInterface` and calling `enable()` method.

Available methods:
- `enable()`: enable synchronous processing
- `disable()`: disable synchronous processing
- `isEnabled()`: check if synchronous processing is enabled

### Index Queue Options

The indexing queue considers the following options:

- **worker_count** (default 1): number of messenger workers to process the queue. Set this to the actual used parallel number of `messenger:consume` workers to improve the calculation of items per batch.
- **min_batch_size** (default 5): minimum number of items to process in one batch (when using multiple workers) 
- **max_batch_size** (default 400): maximum number of items to process in one batch

Based on this configuration, the queue will be processed in batches of `min_batch_size` to `max_batch_size` items. The number of items per batch is calculated based on the number of workers and the number of items in the queue.

Sample configuration:

```yaml
pimcore_generic_data_index:
    index_service:
        queue_settings:
            worker_count: 1
            min_batch_size: 5
            max_batch_size: 400
```

#### Related elements

The indexing queue is automatically populated whenever an element undergoes an update operation. This process includes not only the modified element itself but also any related elements. By default, this indexing occurs asynchronously through Symfony Messenger.

For scenarios requiring immediate processing, you can temporarily switch to synchronous mode by utilizing the `SynchronousProcessingRelatedIdsServiceInterface`.

Available methods are:

| Method | Description |
|--------|-------------|
| `enable()` | Activates synchronous processing mode |
| `disable()` | Reverts to asynchronous processing mode |
| `isEnabled()` | Returns the current processing mode status |

:::info

Currently the `SynchronousProcessingRelatedIdsServiceInterface` interface does not influence the behavior of delete operations. They are always processed synchronously.

:::


### Repairing Indices

Sometimes it might be needed to delete and recreate the index from the Pimcore database
(for example if the mapping changed and cannot be updated).

Do this with the index update command and pass `-r` option (which deletes and recreates the index).
```
# delete index and recreate it
bin/console generic-data-index:update:index -r
```
Without the `-r` option, the index mapping is just updated and all items are added into the queue
for a reindex from the Pimcore database. 


### Updating Index Structure for Data Indices

Index mapping is updated automatically e.g. when adding system languages or new fields to the class definition. 
Sometimes it might be necessary to update the index structure manually.

Do this with the reindex command. This command does native opensearch/elasticsearch re-indexing. So it does not 
index data from the database but reindexes data within the search indices.

```
# updates index mapping with native reindexing
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

By default, the messenger will retry failed messages 3 times and then send them into the failed queue `pimcore_generic_data_index_failed`.
If you want to retry failed messages, you can use the following command:

```
php bin/console messenger:failed:retry -vv
```

For the further commands please refer to the [Symfony Messenger documentation](https://symfony.com/doc/current/messenger.html#saving-retrying-failed-messages).

## Configuring index options (Maximum Item Limit, ...)

You can configure different options to use with your indices. The available options can differ depending on which 
engine you are using. Make sure to check the corresponding documentation, before using any options.

See the `Maxium Item Limit` and `Total fields limit` section for examples.

### Maximum Item limit

A maximum of 10000 items can be retrieved and viewed, because of the maximum default item limit.
To increase this limit, configuration can be adjusted as follows:

```yaml
pimcore_generic_data_index:
    index_service:
        index_settings:
            max_result_window: 20000
```

### Total fields limit

A maximum of 1000 fields can be used with your indces. 
To increase this limit, configuration can be adjusted as follows:

```yaml
pimcore_generic_data_index:
    index_service:
        index_settings:
            mapping.total_fields.limit: 20000
```

:::info

If an index was already created before setting this parameter, the index needs to be recreated.

```
bin/console generic-data-index:update:index -r
```

:::

## Deployment and Index Management

### Pimcore Class Definitions

After every class definition update you should run the following command to update the index structure:

```
php bin/console generic-data-index:deployment:reindex
```

This command will update the index structure for all data object classes which were created/updated since the last deployment and reindex all data objects for relevant classes.

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
