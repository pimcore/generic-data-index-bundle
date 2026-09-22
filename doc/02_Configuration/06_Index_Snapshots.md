# Index Snapshots

A full reindex of a large installation takes hours. A snapshot bundle lets you export all search
indices from one installation and import them into another in minutes, as long as both use the
same class definitions. Typical use: developers import a production database dump together with
the matching index snapshot instead of rebuilding the index locally.

The bundle contains one gzipped JSON-lines file per index with the raw documents, plus a
`manifest.json`. Mappings are **not** exported; the importing installation generates them from its
own class definitions. That makes a bundle portable between OpenSearch and Elasticsearch and
between engine versions.

:::warning

A snapshot contains every indexed value and is as sensitive as a database dump. Store it in a
private location. Never point the storage at a public asset bucket.

:::

## Configuration

```yaml
pimcore_generic_data_index:
    snapshot:
        storage: 'pimcore.generic_data_index_snapshot.storage'  # Flysystem storage service id
        page_size: 1000      # maximum documents per page on export
        page_bytes: 16777216 # raw JSON byte budget per export page (16 MiB)
        bulk_size: 1000      # maximum documents per bulk request on import
        bulk_bytes: 16777216 # raw JSON byte budget per import bulk request (16 MiB)
        import_workers: 4    # worker processes sending bulk requests concurrently on import
```

The bundle ships a private local storage under `var/generic-data-index/snapshots`. Override it in
your `flysystem` configuration to write to object storage, for example S3:

```yaml
flysystem:
    storages:
        pimcore.generic_data_index_snapshot.storage:
            adapter: 'aws'
            visibility: private
            options:
                client: 'assets_s3'
                bucket: '%env(S3_SNAPSHOT_BUCKET)%'
                prefix: 'gdi-snapshots'
```

### Memory and page sizing

The size of an indexed document is not known up front, and one page of 1000 large documents can
exhaust PHP's memory limit on its own. `page_size` and `bulk_size` are therefore ceilings, not
fixed batch sizes:

- On export, the first page of every index requests a single document, which limits the initial
  exposure to one document while nothing is known about document size yet. Every following page is sized
  from the larger of two estimates: the average over the documents written for that index so far,
  and the average of the most recent page. The second one makes a run of larger documents shrink
  the next page immediately, while a single outlier does not collapse the page size. Small
  documents still page at `page_size`; 200 KB documents page at about 80 with the default budget.
- On import, a bulk request is sent as soon as either `bulk_size` documents or `bulk_bytes` of raw
  JSON are pending. Keep `bulk_bytes` well below the search engine's request size limit
  (`http.max_content_length`, 100 MB by default).
- `import_workers` bulk requests are in flight at once. The importing process cuts the snapshot
  file into bulk bodies on disk and hands them to that many worker processes, each of which boots
  the application once and sends what it is given; the search engine indexes the requests on its
  write threads in parallel. With one worker the importing process sends everything itself. A
  request the engine rejects because its write queue is full (HTTP 429) is retried with backoff,
  up to five attempts, so more workers than the engine can take cost time but no documents.
  Measured on a 2 million document import on a 12-core notebook: 4 workers were 3 times as fast
  as 1, 8 workers 3.4 times; beyond that the node rejected requests. Unsent bulk bodies occupy at
  most `2 × import_workers × bulk_bytes` in the system temp directory.

The budget is a target rather than a guarantee, because the search engine cannot be asked for "at
most N bytes": a page whose documents are larger than everything seen before is only corrected
afterwards. A 16 MiB page decodes to roughly 100–200 MB of PHP memory at its peak. Lower the budgets on hosts
with a small `memory_limit`; raising them buys little, because the number of requests is rarely
the bottleneck. Both commands log every page and every bulk flush at debug level.

Export and import share one lock, so they never run at the same time. The lock is taken from
Symfony's lock component (`framework.lock`), whose default store is a host-local file lock. On an
installation with several application nodes, configure a shared lock store (for example Redis or
the database) so that an export on one node also excludes an import on another.

Run both commands with `--no-debug` (or in the `prod` environment). In debug mode the bundle keeps
a history of executed searches, including their full responses, for the profiler; on a large
export this history alone can exhaust the memory limit.

## Export

```bash
bin/console generic-data-index:snapshot:export [--name=NAME] [--dry-run]
```

Run it right after the database dump so both come from the same window. Both queue counts (before
the export, and after, once it finished) are recorded in the manifest; if the queue held any
entries before the export started, the command prints a note that the index queue was not idle and
the snapshot may lag the database.

Each index is written to its own file first; the manifest is written last, once every index has
been exported successfully. If the export fails at any point — including a failure to write the
manifest itself — the partial snapshot directory is deleted, so an interrupted export never leaves
a snapshot that looks complete. `--dry-run` resolves the indices and their current document counts
and prints them without writing anything ("Nothing written.").

Snapshots are kept in the storage until an operator deletes them from it; running
`generic-data-index:snapshot:import` without a name uses the newest one.

An index that does not exist on the source installation (for example a class that has never been
indexed there) is exported as an empty index. The import then recreates that index empty, so a
stale local index with old documents is replaced rather than silently kept.

The export pages through each live alias without taking a point-in-time snapshot of it, so
elements that are created, changed, or deleted while the export is running can be reflected
inconsistently across documents (or across pages of the same index). Run the export in a quiet
window — right after the database dump, with idle messenger consumers — to keep this window as
small as possible, and treat the manifest's `queue_entries_before` and `queue_entries_after` counts
as the indicator of how clean the resulting baseline is: the closer both are to zero, the less
concurrent activity the export could have raced.

## Import

```bash
bin/console generic-data-index:snapshot:import [NAME] [--from-path=DIR] [--force] [--dry-run]
```

Without `NAME` the newest complete snapshot in the configured storage is used. `--from-path` reads
the snapshot from an already-downloaded local directory instead of the configured storage (for
example after copying it down from object storage by hand). Before writing anything the command
compares the class mapping checksums in the manifest with the local settings store and class
definitions. The local class definition is always the reference for compatibility; the settings
store only decides between an already-consistent index and one that merely has a stale checksum
stamp:

| Manifest checksum | Result |
|---|---|
| computed from the local class definition equals the manifest and the settings store matches | imported |
| computed equals the manifest, settings store differs (stale) | imported, store re-stamped |
| computed differs from the manifest | refused; `--force` skips that class |
| no checksum in the manifest for a class that has an index | refused; `--force` skips that class |

A class that is present in the manifest but no longer defined locally has no local index to import
into and is always skipped, independently of `--force`. A checksum entry for a class that has no
index in the manifest gates nothing, because the import never touches that class's index; the
class is only listed as missing in the manifest. If the check finds a mismatch and
`--force` was not given, the command refuses the whole import and lists the affected classes; pass
`--force` to import everything else and skip only those classes.

Before any index is touched, the command downloads the file of every index it is going to import
and verifies its size and SHA-256 against the manifest, so a truncated or corrupted bundle is
rejected while all local indices are still intact. Files of classes that are skipped (via
`--force`, or because the class no longer exists locally) are never imported and therefore not
downloaded or verified.

If the local index queue is not empty, the command prints a warning ("Stop messenger consumers
during the import to avoid interleaved writes.") but still proceeds — stopping the consumers
yourself is recommended, not enforced. The import then recreates each index with the local
mapping and replays the documents through the bulk API; it does **not** enqueue elements. The
documents are sent to the bulk API exactly as stored in the snapshot, without being decoded and
re-encoded, and while an index is being replayed its automatic refresh is disabled and its
translog switched to asynchronous durability; the index is flushed (made durable) and both
settings are restored as soon as the index is complete (or the replay failed). Before a
class index is recreated, its stored mapping checksum is removed and only stamped again once the
replay completed: should the replay fail, the class is left without a checksum, so the normal
per-class reindex (`generic-data-index:deployment:reindex`, or the class-definition update) rebuilds the emptied
index instead of skipping it as unchanged. After a class definition change, that per-class
reindex still applies as usual.

`--dry-run` runs the compatibility check and prints the import plan (which indices, how many
documents each) without writing anything ("Nothing written."). A real import exits with a non-zero
status if the compatibility check refuses the snapshot, if the snapshot or `--from-path` directory
cannot be found, or if the number of documents actually written to any index does not match the
manifest afterwards — in that last case the indices already written are **not** rolled back.

## Pimcore PaaS

On Pimcore PaaS (Upsun) the default local storage lands on the `/app/var` mount, which is not
reachable without the platform CLI. Point the storage at object storage and run the export from a
cron next to your database dump:

```yaml
crons:
    gdi_snapshot:
        spec: "0 2 * * 0"
        commands:
            start: "php /app/bin/console generic-data-index:snapshot:export"
```
