# Operations Guide

This guide is for operators and developers who run the Generic Data Index 2.5 with large indices and handle
deployments, class changes, upgrades and day-to-day index management. Which change needs which reindex is described
in the [Reindexing Guide](../03_Reindexing/README.md); the commands and configuration options are described in
[Index Management](./03_Index_Management.md).

Commands are written as in the Reindexing Guide: without their `generic-data-index:` prefix (`update:index`,
`deployment:reindex`), except `generic-data-index:reindex`. Some of them need a recent 2.5 release: the index options
of `deployment:reindex` (`--all`, `--classes`, `--dry-run`, …) exist since 2.5.14, `snapshot:export` and
`snapshot:import` since 2.5.13, and `generic-data-index:status` since 2.5.9 (see the
[upgrade notes](../01_Installation/02_Upgrade.md)).

## When This Guide Applies

Two operations grow with the number of documents in an index:

- The **odd/even copy**: the search engine copies all documents of an index into the other `-odd`/`-even` index
  (`_reindex`), then the bundle switches the alias. See
  [How Reindexing Works](../03_Reindexing/README.md#how-reindexing-works).
- The **rebuild from the database**: messenger workers load every element in the index queue from the database and
  write its document.

With a few thousand documents both finish in seconds. With millions of documents they take hours, and the defaults of
the bundle (a budget of one hour per copy, one messenger worker) no longer fit. Apply this guide when an index holds
about one million documents or more, or when the estimate under [Capacity Planning](#capacity-planning) gives more
than a few minutes. `bin/console generic-data-index:status` lists every index with its document count and size.

## What Runs Where

A copy runs as a task in the search engine. The PHP process that started it waits until the task has finished and
polls its status; it is blocked for the whole copy. Whether that process is a messenger worker or a console command
depends on the trigger:

| Trigger | Copy runs in | Rebuild from the database |
| --- | --- | --- |
| Class saved in the admin UI or Studio, mapping checksum changed | A messenger worker (`UpdateClassMappingMessage`) | All objects of the class |
| Object brick added to or removed from a class | A messenger worker (Pimcore saves the class) | All objects of the class |
| `pimcore:deployment:classes-rebuild` | A messenger worker, one message per changed class (every class with `-f`) | All objects of each changed class |
| Valid languages changed in the system settings | One messenger worker: one message copies every class, asset and document index, one after the other | Every element |
| `deployment:reindex`, with or without options | The console process | See [Which Trigger Does What](../03_Reindexing/README.md#which-trigger-does-what) |
| `update:index` | The console process, only if the in-place mapping update is rejected | Everything in scope |
| `update:index -r` | No copy: the indices in scope are deleted and created empty | Everything in scope |
| `generic-data-index:reindex` | The console process, every class, asset and document index | None (see [Commands](../03_Reindexing/README.md#commands)) |
| Class definition deleted | No copy: the index is deleted during the request | None |

A class save copies only if the class's mapping differs from the checksum stored after the last successful update.
Some changes alter the mapping without a class save: a bundle upgrade with a mapping change, changes to field
collections, object bricks or the classification store, valid languages in config files, or `ExtractMappingEvent`
code. They leave the stored checksum outdated. If `deployment:reindex` doesn't run after such a change, the next save
of an affected class starts the copy, even if that save only changes a title or a tooltip. On a large class the copy
then runs in a messenger worker during working hours.

Every save of a class definition and every save of the system settings runs `messenger:stop-workers`, whether the
mapping changed or not. All messenger workers stop after their current message, so a process manager has to start
them again (see [Messenger Workers](#messenger-workers)). A worker in the middle of a copy finishes the copy first.

## Capacity Planning

### Copy Time

The duration of a copy follows the amount of data the engine writes into the new index. Estimate it as the number of
documents times the time per document.

As an example: on a development machine with Generic Data Index 2.5, OpenSearch 2, a 1 GB heap and documents of 63
fields and about 6 KB, a copy took about 1.2 ms per document: about 106 seconds for 100,000 documents and about 370
seconds for 300,000. At that rate, 3 million documents take about one hour and 10 million about 3.3 hours. Your
cluster can be faster or slower: more CPU and heap speed it up, larger documents, replicas and concurrent load slow it
down. Measure on a staging system with production-like data, for example by timing `deployment:reindex` after
deploying a new field in a large class (with the workers stopped, see [Deployment Procedure](#deployment-procedure)),
or by reading `running_time_in_nanos` of the task (see [Monitoring a Running Copy](#monitoring-a-running-copy)).

Each copy of an index has to fit into the budget of `reindex_settings` (see [Copy Time Budget](#copy-time-budget)).
How a field type change is copied is described in the [Reindexing Guide](../03_Reindexing/README.md).
`generic-data-index:reindex` and a language change in the system settings copy every index one after the other, so
their total time is the sum over all indices.

### Rebuild Time

Estimate the rebuild as the number of elements divided by the indexing rate of all workers together. On the
development machine above, one worker indexed about 300 documents per second, so 10 million objects take about
9 hours with one worker. More workers only help as long as the database and the engine keep up. The rate depends on
the class (relations, calculated values in `live` mode, related elements) and on the database. While the rebuild
runs, the index queue table holds one entry per element.

A class save with a mapping change and `deployment:reindex` with a mapping change queue the rebuild of all objects of
the class, even if the change only added a field.

### Disk Space

While a copy runs, the old and the new index exist side by side. Plan free disk space for at least the size of the
index being copied, plus room for segment merges, for every copy that runs at the same time. Copies run at the same
time when several workers handle class messages (for example after `classes-rebuild -f` or several class saves), or
when a console command and a worker copy different indices. Indices left behind by aborted copies take space until
they are removed (see [Housekeeping](#housekeeping)).

When a node passes the engine's flood-stage disk watermark (`cluster.routing.allocation.disk.watermark.flood_stage`,
default 95% on OpenSearch 2 and Elasticsearch 8), the engine blocks writes to every index with a shard on that node,
including the live indices the messenger workers write to. Elasticsearch also limits the threshold by a maximum
headroom, and OpenSearch doesn't apply the watermarks on a single data node by default
(`enable_for_single_data_node: false`); a full disk then fails the writes directly. See the disk-based shard
allocation settings of your engine.

### Shards, Replicas and Index Settings

The default index settings (`config/pimcore/default_index_settings.yaml`) use `number_of_shards: 1` and
`number_of_replicas: 0`: each index lives in one shard on one node, without a copy on another node. Follow your
engine's shard sizing guidance for large indices.

`index_settings` apply to every index the bundle creates; there is no setting per index. Raising the number of shards
for one large class raises it for every class, asset and document index, and the shard count of the cluster is the
number of indices times shards times (1 + replicas). Settings only apply when an index is created:
`deployment:reindex --all` copies the indices whose settings changed (see
[Other Index Changes](./03_Index_Management.md#other-index-changes) for the first run, which only stores a baseline).

The bundle creates the new index of a copy with the configured settings, including replicas, and submits one
`_reindex` task per index without slicing. It doesn't lower the replicas or the refresh interval during the copy.
Replicas therefore multiply the write load of every copy.

## Configuration for Large Indices

### Copy Time Budget

The bundle polls a copy task every `poll_interval` seconds, at most `max_polls` times, and aborts the copy when the
budget is used up (see [Reindex Options](./03_Index_Management.md#reindex-options)). The defaults give one hour per
copy. Set the budget from your estimate with a margin:

```
max_polls >= documents × seconds per document ÷ poll_interval × 2
```

Example: 10 million documents at 1.2 ms each take 12,000 seconds; with `poll_interval: 5` that is 2,400 polls, doubled
4,800 polls (about 6.7 hours):

```yaml
pimcore_generic_data_index:
    index_service:
        reindex_settings:
            max_polls: 4800
            poll_interval: 5
```

The budget applies to every index and every trigger. Size it for the largest index, and size the redelivery of the
messenger workers to match (see below).

### Messenger Workers

All messages of the bundle use one transport, `pimcore_generic_data_index_queue`: index queue batches, the dispatch
message, related elements, and the class mapping and language messages that run copies
(`config/pimcore/messenger.yaml`). The transport is the Doctrine transport of the default database connection
(`doctrine://default?queue_name=pimcore_generic_data_index_queue`). A worker that handles a class mapping message is
blocked for the whole copy.

- Run **at least two workers**, so the index queue keeps moving while one worker copies. Run more for the rebuild
  throughput, and set `queue_settings.worker_count` to the number of workers (see
  [Index Queue Options](./03_Index_Management.md#index-queue-options)).
- One worker must also consume `scheduler_generic_data_index`. It moves index queue entries to the transport every
  10 seconds:

  ```bash
  bin/console messenger:consume pimcore_generic_data_index_queue scheduler_generic_data_index
  ```

- Run the workers under a **process manager that restarts them**: class and system settings saves stop all workers
  (see [What Runs Where](#what-runs-where)).
- `--time-limit` and `--memory-limit` are checked between messages, so they don't interrupt a copy. A process manager
  or platform that kills a worker during a copy (stop timeout, restart on deployment, container shutdown) leaves the
  engine task running and its new index behind. The Doctrine transport hands the message out again after
  `redeliver_timeout` (see below), and the next copy of that index cancels the leftover task and starts over. Give
  workers a stop timeout that fits your copies, or stop them only when no copy runs.
- A copy that takes longer than the database `wait_timeout` doesn't break the worker: the checksum is stored and the
  message is acknowledged after the copy as usual. This was tested on the 2026.3 line; the code path is the same in
  2.5.

**Redelivery.** The Doctrine transport hands a message that is still being handled after `redeliver_timeout`
(default 3600 seconds) to another worker. A class mapping message whose copy takes longer than an hour is then handled
by two workers at the same time, which must not happen (see the warning under
[When a Copy Fails, Times Out or Is Cancelled](#when-a-copy-fails-times-out-or-is-cancelled)). 2.5 runs on Symfony 6.4
or 7, and what you can do depends on the version:

- **Symfony 7.3 or newer:** start the workers with `--keepalive`, which keeps the message marked as being handled.
  The keepalive interval must be smaller than `redeliver_timeout`. `--keepalive` needs the `pcntl` PHP extension; it
  renews the message every 5 seconds by default and doesn't shorten the copy time budget.

  ```bash
  bin/console messenger:consume pimcore_generic_data_index_queue scheduler_generic_data_index --keepalive
  ```

- **Symfony 6.4, or 7 before 7.3:** the Doctrine transport has no keepalive. Raise `redeliver_timeout` above the
  longest copy you expect. This works on newer versions as well.

  ```yaml
  framework:
      messenger:
          transports:
              pimcore_generic_data_index_queue:
                  options:
                      redeliver_timeout: 28800
  ```

Options set in the application config are merged into the bundle's transport definition; its DSN and failure transport
stay as they are.

**Retries.** The bundle sets no retry strategy, so Symfony's default applies: 3 retries after about 1, 2 and 4
seconds, then the message goes to `pimcore_generic_data_index_failed`. A copy that ran out of its budget is retried
from the start, each time with the full budget: one class save can copy the same index up to four times. A retry
strategy set on the transport in the application config replaces the default and applies to all messages of the
bundle, including index queue batches.

**Dedicated worker for copies.** To keep copies away from the index queue workers, route the class mapping and
language messages to a transport of their own and run one worker for it:

```yaml
framework:
    messenger:
        transports:
            pimcore_generic_data_index_mapping:
                dsn: 'doctrine://default?queue_name=pimcore_generic_data_index_mapping'
                failure_transport: pimcore_generic_data_index_failed
                options:
                    redeliver_timeout: 28800
        routing:
            Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateClassMappingMessage: pimcore_generic_data_index_mapping
            Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateLanguageSettingsMessage: pimcore_generic_data_index_mapping
```

A route in the application config replaces the bundle's route for that message; the other messages stay on
`pimcore_generic_data_index_queue`.

```bash
bin/console messenger:consume pimcore_generic_data_index_mapping
```

## Deployment Procedure

During a deployment, class changes reach the index in two ways: `classes-rebuild` sends one message per changed class
to the workers, and `deployment:reindex` copies the changed classes in the console process. Both compare the class
mapping with the checksum that is stored only after a successful update. If the workers are running, a worker and the
console can therefore copy the same class at the same time. Stop the workers first:

1. **Prepare.** On a staging system with the new code and production-like data, run
   `deployment:reindex --all --dry-run` to see which indices will be copied or rebuilt, and estimate copy and rebuild
   time. `--dry-run` needs an index option such as `--all`. Check free disk space, `reindex_settings` and the worker
   settings against the estimate. Take a backup if the change is risky (see
   [Backups and Rollback](#backups-and-rollback)).
2. **Stop the workers in the process manager** (for example `supervisorctl stop`, or scale the worker deployment to
   zero). `messenger:stop-workers` is not enough: the process manager starts them again.
3. **Deploy** the code and run `pimcore:deployment:classes-rebuild`. Its messages wait in the transport.
4. **Run `deployment:reindex --all`.** The copies run in this process; the elements to rebuild are added to the index
   queue.
5. **Start the workers.** The waiting class messages find the new checksums and skip the copy; the workers process
   the rebuild. If step 4 failed for a class, its message starts the copy again in a worker.
6. **Watch** `generic-data-index:status` until the index queue has drained.

Notes:

- Run steps 3 to 5 on one node. The commands lock against a second run of themselves only, and the lock is host-local
  unless a shared lock store is configured.
- Step 4 runs as long as the copies. Set the timeout of the CI/CD job above your estimate, or run the command as a
  separate job whose output is kept.
- With an index option (`--all`, `--classes`, …) a failing index doesn't stop the others; the command reports
  "Finished with errors" and exits with a non-zero status. Without options, the first class that fails stops the run.
- Search keeps working during the deployment: reads go to the old index until the alias of each index switches.
  Element saves made while the workers are stopped wait in the index queue and are processed after step 5. Writes
  that a running worker processes during a copy go to the old index and are lost at the switch; a rebuild queued
  after the copy writes them again, except deletes. A copy that only applies changed `index_settings` queues no
  rebuild, so such writes stay lost until the element is saved again. This is why the workers are stopped.

## Class Changes in Production

Saving a class in the admin UI or Studio with a mapping change starts the copy in a worker about two seconds later,
followed by the rebuild of all objects of the class. On a class with millions of objects this blocks a worker for
hours, needs the disk space of a second copy, and keeps the workers busy with the rebuild. Make such changes in a
deployment with the procedure above, and consider who may edit class definitions in production.

- Before editing a large class, run `deployment:reindex --all --dry-run`. If it reports a mapping change you didn't
  make (see the outdated checksums under [What Runs Where](#what-runs-where)), your save will copy the class even if
  your change doesn't touch the mapping.
- Changing the class icon updates every document of the class whose icon differs, with one update-by-query request
  in the worker that handles the class message. The request stays open until the engine has finished; measured on the
  2026.3 line, which uses the same request, it took about 20 seconds for 100,000 documents, so plan minutes for
  millions.
- For a field type change, see the [Reindexing Guide](../03_Reindexing/README.md): it describes when the index is
  recreated, and the expand and contract approach that avoids it.

## Monitoring a Running Copy

The bundle logs when a class copy starts, but not its progress. Use the search engine and the commands below.

Running copy tasks, with the source and target index in the description and the progress in `status.total` and
`status.created`:

```
GET _tasks?actions=*reindex&detailed=true
```

Indices with their document counts; the count of the new index grows during the copy:

```
GET _cat/indices/<index_prefix>*?v&s=index
```

`generic-data-index:status` shows every index with its document count and size, and the index queue depth. While a
copy runs, the index exists as both `-odd` and `-even`; the command warns that this usually indicates an interrupted
reindex. Check the tasks API before you treat it as a leftover. After the copy, the queue depth shows the progress of
the rebuild.

Log lines of the `pimcore_generic_data_index` channel:

| Log line | Meaning |
| --- | --- |
| `Reindexing class mapping` (info, with stored and current checksum) | A class copy starts |
| `Creating index <name>` (info) | The new index of a copy is created |
| `Failed to fetch status of reindex task ...` (warning) | A status poll failed; the bundle retries |
| `Reindex task ... did not complete within N seconds` (error message of the failure) | The copy budget ran out |
| `Keeping index "...": ... may still write into it` (warning) | The task could not be confirmed as cancelled; the index stays until the next copy |
| `Cancelling leftover reindex task ...` (warning) | A copy found a task from an earlier aborted copy and cancels it |
| `Recreating index for alias "..."` (warning) | The existing documents don't fit the new mapping; the index is recreated empty |

Messenger: `bin/console messenger:stats` shows the messages waiting per transport, and
`bin/console messenger:failed:show --transport=pimcore_generic_data_index_failed` the failed ones.

## When a Copy Fails, Times Out or Is Cancelled

| Case | What happens | What to do |
| --- | --- | --- |
| The copy runs out of its budget (`did not complete within N seconds`) | The bundle cancels the task and deletes the new index. The old index stays live with the old mapping, the checksum is not stored, nothing is queued. A console command exits with a non-zero status; a worker retries the message 3 times, then moves it to the failed transport. | Raise `max_polls` (and the worker redelivery), then run `deployment:reindex --all` in a quiet period. It copies the classes whose checksum was not stored. Failed messages for these classes skip the copy once the checksum matches. |
| The task reports an error, or the cluster is unreachable | As above. Single failed status polls are retried and don't abort the copy. | Fix the cause, then as above. |
| The existing documents don't fit the new mapping | The index is recreated empty with the new mapping (`Recreating index for alias`); see the [Reindexing Guide](../03_Reindexing/README.md). | Run enough workers for the rebuild and watch `generic-data-index:status`. After `generic-data-index:reindex`, which queues nothing, run `update:index -c <id>` or `-a`. |
| The task could not be confirmed as cancelled | The new index is kept (`Keeping index` warning). | The next copy of the index cancels the leftover task and deletes the index; or remove it by hand (see [Housekeeping](#housekeeping)). |
| The worker or console process was killed during the copy | The engine task keeps running; its new index stays. | The next copy of the index cancels the task. Check the tasks API before you start one by hand. |
| Valid languages changed in the system settings, and the copies failed | The error is logged (`Updating languages failed`); the message is not retried. | Run `deployment:reindex --all`. Indices it reports with "no stored state yet" need `update:index` once (see [Other Index Changes](./03_Index_Management.md#other-index-changes)). |
| `deployment:reindex` without options fails for one class | The classes after it are not processed in this run. | Fix the cause and run the command again, or use `--all`, which continues with the other indices. |

:::warning

Don't cancel a running copy task of the bundle by hand (`POST _tasks/<task_id>/_cancel`), and don't let two copies of
the same index overlap. The engine reports a cancelled task as finished without errors, and versions without the fix
for [#639](https://github.com/pimcore/platform-version/issues/639) take it as a complete copy: the alias switches to
the incomplete index and the old index is deleted. When two copies of the same index overlap, the second one cancels
the first (it removes running copies of its target before it starts), and the class index can disappear completely.
With the fix, a cancelled copy fails and the alias stays on the complete index, but an overlapping copy still starts
the first one over.

Copies of the same index overlap when workers run during `classes-rebuild` and `deployment:reindex` (see
[Deployment Procedure](#deployment-procedure)), when a message is redelivered after `redeliver_timeout`, or when a
class is saved twice within the copy time and two workers handle the messages.

To stop a copy, first stop the process that waits for it (the worker or the console command), then cancel the task.
The next copy of the index removes the incomplete index.

:::

## Backups and Rollback

The bundle deletes the old index right after the alias switch, so there is no previous index to switch back to.
Reverting a class change is another mapping change: it runs another copy and another rebuild, with the same duration.

Before a risky change on large indices (a field type change, an upgrade with a mapping change, `update:index -r`),
take a backup of the indices together with a database dump:

- If the cluster has a snapshot repository, take a native snapshot of the engine. Restoring it needs the database
  dump from the same moment, because the settings store holds the class mapping checksums. See
  [Index Snapshots](./06_Index_Snapshots.md) for the restore steps.
- Without a snapshot repository, for example on Pimcore PaaS, `snapshot:export` writes a portable bundle (see
  [Index Snapshots](./06_Index_Snapshots.md)).

## Bundle Upgrades That Change Mappings

An upgrade note that changes a mapping (marked "Re-indexing required" or describing a mapping change in the
[upgrade notes](../01_Installation/02_Upgrade.md)) changes the checksums of the affected classes. Nothing happens
until `deployment:reindex` runs, or until the next save of an affected class copies it in a worker.

1. Install the new version on staging and run `deployment:reindex --all --dry-run` to list the indices it will copy
   or rebuild.
2. Estimate copy and rebuild time per index (see [Capacity Planning](#capacity-planning)).
3. Deploy with the [Deployment Procedure](#deployment-procedure) in a planned window.

Where an upgrade note names `update:index` without options, be aware that it adds every element of every index to
the index queue. Limit it with `-c` or `-a` where the note allows; 2.5 has no option for the document index alone.

## Housekeeping

- **Leftover indices.** An index that exists as both `-odd` and `-even` without a running copy task is a leftover of
  an aborted copy. 2.5 has no command to remove it; delete it in the search engine, after three checks:

  ```
  GET _cat/aliases/<index_prefix>*?v
  GET _tasks?actions=*reindex&detailed=true
  DELETE /<leftover index>
  ```

  Delete only the index that the alias doesn't point to, and only if no task names it as source or target: while a
  copy runs, its new index has no alias yet, and deleting it breaks the copy. On large indices a copy can run for
  hours, so check the tasks API right before the delete.
- **Mapping growth.** Removed fields and languages stay in the mapping after a copy or an in-place update and count
  against `index.mapping.total_fields.limit` (see [Total fields limit](./03_Index_Management.md#total-fields-limit)).
  Only `update:index -r` removes them, and it leaves the index empty until the rebuild has finished. On large indices
  plan it like a deployment, with the rebuild time, or leave the entries.
- **Deleted elements.** If a worker processed the delete of an element during a copy, the element stays in the new
  index, and a rebuild doesn't remove it. Only `update:index -r` removes such documents (see
  [Known Limitations](../03_Reindexing/README.md#known-limitations)).

## Troubleshooting

| Symptom | Check | Action |
| --- | --- | --- |
| A class change is not searchable or filterable after a deployment | `deployment:reindex --all --dry-run` | Run `deployment:reindex --all`; field collection, object brick and classification store changes are never applied by a class save alone |
| A command or worker fails with `did not complete within N seconds` | Document count of the index, `reindex_settings` | Raise `max_polls`, then run `deployment:reindex --all` |
| `generic-data-index:status` warns about `-odd` and `-even` | `GET _tasks?actions=*reindex&detailed=true` | Task running: wait. No task: remove the leftover by hand (see [Housekeeping](#housekeeping)) |
| The index queue doesn't drain | Are workers running? Does one consume `scheduler_generic_data_index`? `messenger:stats`, failed messages | Start the workers, consume the scheduler transport, handle failed messages |
| Indexing stalls for a long time after a class save | Tasks API, log line `Reindexing class mapping` | A worker is copying; wait, or run more workers or a dedicated worker for copies |
| The index is empty or incomplete after a class change | Log line `Recreating index for alias` | Let the rebuild run; watch `generic-data-index:status` |
| An index is empty after `generic-data-index:reindex` | Log line `Recreating index for alias` | `update:index -c <id>` or `-a` |
| Elements saved during a copy show old values | Did the copy queue a rebuild (settings-only copy, `generic-data-index:reindex`)? Were workers running? | `update:index -c <id>` or `-a`; `update:index` for documents |
| Deleted elements are still found | Was the element deleted during a copy? | `update:index -r` for that index in a planned window |
| A harmless class save started a long copy | `deployment:reindex --all --dry-run` before the save | Run `deployment:reindex --all` after every deployment and upgrade |
| Messages in `pimcore_generic_data_index_failed` | `messenger:failed:show --transport=pimcore_generic_data_index_failed` | Fix the cause, then `messenger:failed:retry --transport=pimcore_generic_data_index_failed` |
| Workers restart during the day | Class or system settings saves | Expected; make sure the process manager restarts them |
| Writes rejected, disk usage high | Engine disk allocation, leftover indices | Remove leftovers, add disk space before the next copy |
| Total fields limit exceeded | Field count of the mapping | See [Total fields limit](./03_Index_Management.md#total-fields-limit) |
