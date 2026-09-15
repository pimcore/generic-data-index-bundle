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
        keep: 3            # complete snapshots kept after an export, 0 = unlimited
        page_size: 1000    # documents per page on export
        bulk_size: 1000    # documents per bulk request on import
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

## Export

```bash
bin/console generic-data-index:snapshot:export [--name=NAME] [--max-queue-entries=N] [--wait=SECONDS] [--dry-run]
```

Run it right after the database dump so both come from the same window. `--max-queue-entries`
refuses to export while the index queue is deeper than `N`; with `--wait` the command polls the
queue every 5 seconds until it drains or the timeout is reached. Both queue counts (before the
export, and after, once it finished) are recorded in the manifest.

Each index is written to its own file first; the manifest is written last, once every index has
been exported successfully. If the export fails at any point — including a failure to write the
manifest itself — the partial snapshot directory is deleted, so an interrupted export never leaves
a snapshot that looks complete. `--dry-run` resolves the indices and their current document counts
and prints them without writing anything ("Nothing written.").

After a successful export the command keeps only the `keep` newest complete snapshots in the
storage and deletes the rest. If that rotation step itself fails (e.g. a storage error while
deleting an old snapshot), the command prints a warning — the newly written snapshot is still
valid and the command still exits successfully; only the cleanup did not fully complete.

The export pages through each live alias without taking a point-in-time snapshot of it, so
elements that are created, changed, or deleted while the export is running can be reflected
inconsistently across documents (or across pages of the same index). Run the export in a quiet
window — right after the database dump, with `--max-queue-entries` and idle messenger consumers —
to keep this window as small as possible, and treat the manifest's `queue_entries_before` and
`queue_entries_after` counts as the indicator of how clean the resulting baseline is: the closer
both are to zero, the less concurrent activity the export could have raced.

## Import

```bash
bin/console generic-data-index:snapshot:import [NAME] [--from-path=DIR] [--only=asset,data-object_product] [--force] [--dry-run]
```

Without `NAME` the newest complete snapshot in the configured storage is used. `--from-path` reads
the snapshot from an already-downloaded local directory instead of the configured storage (for
example after copying it down from object storage by hand). Before writing anything the command
compares the class mapping checksums in the manifest with the local settings store and class
definitions:

| Manifest checksum | Result |
|---|---|
| equals the local settings store | imported |
| equals the checksum of the local class definition, store differs | imported, store re-stamped |
| matches neither | refused; `--force` skips that class |
| no checksum in the manifest for a class that has an index | refused; `--force` skips that class |

A class that is present in the manifest but no longer defined locally has no local index to import
into and is always skipped, independently of `--force`. If the check finds a mismatch and
`--force` was not given, the command refuses the whole import and lists the affected classes; pass
`--force` to import everything else and skip only those classes.

`--only` limits the import to the given comma-separated short index names (e.g.
`asset,data-object_product` for the asset index and the `Product` class index); every other index
in the snapshot is left untouched. A name that does not exist in the snapshot fails the command
before anything is written.

If the local index queue is not empty, the command prints a warning ("Stop messenger consumers
during the import to avoid interleaved writes.") but still proceeds — stopping the consumers
yourself is recommended, not enforced. The import then recreates each selected index with the
local mapping and replays the documents through the bulk API; it does **not** enqueue elements.
After a class definition change, the normal per-class reindex still applies.

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
            start: "php /app/bin/console generic-data-index:snapshot:export --max-queue-entries=100 --wait=600"
```
