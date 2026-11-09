---
title: Diagnostics
description: Read-only diagnostic checks of the Generic Data Index for the Pimcore diagnostics bundle.
---

# Diagnostics

The Generic Data Index provides read-only diagnostic checks through the Pimcore diagnostics contract
(`Pimcore\Contracts\Diagnostics`). The Pimcore diagnostics bundle collects and runs them, for example to answer
"what is the state of search?"; the bundle itself never runs them.

Requires Pimcore 2026.4 and the Pimcore diagnostics bundle; without them the checks are not registered.

Both checks are tagged `pimcore.diagnostics.check` with the topic `search`. They change nothing on the instance.
Every message, subject and reason is a fixed text; no value read from the instance is embedded into it except a
validated index name. A check that cannot read its source reports a "not available" entry instead of an error, and
never the text of the underlying exception.

## index_queue_backlog

Check id `generic_data_index.index_queue_backlog`: how many index queue entries wait and for how long.

**Metrics**

| Field                        | Type        | Meaning                                                                    |
|------------------------------|-------------|----------------------------------------------------------------------------|
| `queue_total`                | int         | All entries of the index queue table.                                      |
| `queue_dispatched`           | int         | Entries handed to the messenger transport and not yet processed.           |
| `queue_pending`              | int         | Entries waiting to be dispatched.                                          |
| `oldest_pending_age_seconds` | int or null | Age of the oldest pending entry in seconds; null when nothing is pending.  |
| `oldest_dispatched_age_seconds` | int or null | Age of the oldest dispatched entry in seconds; null when nothing is dispatched. |
| `client_type`                | string      | Configured search client type (`openSearch` or `elasticsearch`).           |
| `client_name`                | string      | Configured search client name.                                             |
| `index_prefix`               | string      | Configured index prefix.                                                   |

The configuration fields are reported only when they look like identifiers (letters, digits, `.`, `_`, `-`);
any other value is reported as `null`.

**Findings** (subject `generic_data_index_queue`)

| Code                       | Severity | Condition                                        | Message                                                                 |
|----------------------------|----------|--------------------------------------------------|-------------------------------------------------------------------------|
| `index_queue_backlog`      | warning  | oldest pending entry older than 3600 seconds     | Index queue entries older than one hour are waiting.                    |
| `index_queue_backlog`      | critical | oldest pending entry older than 86400 seconds    | Index queue entries older than one day are waiting.                     |
| `index_queue_not_consumed` | warning  | oldest dispatched entry older than 3600 seconds  | Dispatched index queue entries older than one hour are not processed.   |
| `index_queue_not_consumed` | critical | oldest dispatched entry older than 86400 seconds | Dispatched index queue entries older than one day are not processed.    |

Pending entries wait to be dispatched to the messenger transport; dispatched entries wait for a messenger worker.
`index_queue_not_consumed` therefore usually means that no worker consumes the generic data index transport. Both
ages count from the entry's operation time (when it was queued), so an entry that waited long before it was
dispatched also counts as an old dispatched entry.

**Not available** (subject `generic_data_index_queue`)

- `table_missing`: the index queue table does not exist (e.g. the bundle's migrations did not run). Only the
  configuration fields are reported.
- `not_readable`: the table could not be read. No metrics are reported.

**Never returned:** element ids, queue payload, search hosts and credentials, exception messages.

**Sources:** `generic_data_index_queue` (one aggregate query, bounded to two seconds on MariaDB and MySQL), the
search index configuration (client type, client name, prefix).

## index_health

Check id `generic_data_index.index_health`: health, document count and size of the search indices of the
configured index prefix, read with one `_cat/indices` call through the bundle's configured search client.

**Metrics**

| Field              | Type | Meaning                                                                               |
|--------------------|------|---------------------------------------------------------------------------------------|
| `index_count`      | int  | Indices whose name starts with the configured prefix.                                 |
| `indices_red`      | int  | Indices with health `red`.                                                            |
| `indices_yellow`   | int  | Indices with health `yellow`.                                                         |
| `documents_total`  | int  | Sum of the indices' document counts as reported by the search engine (this count includes nested sub-documents, so it can be higher than the number of indexed elements). |
| `size_bytes_total` | int  | Sum of the indices' store size in bytes (primaries and replicas).                     |

**Findings**

| Code                    | Severity                         | Subject        | Message                                                    |
|-------------------------|----------------------------------|----------------|------------------------------------------------------------|
| `index_not_green`       | critical (red), warning (yellow) | the index name | The index is not green.                                    |
| `no_indices_for_prefix` | warning                          | none           | No search index exists for the configured index prefix.   |

`index_not_green` is reported for at most 20 indices, red ones first; the metrics count all of them. The index name is used as
the subject only when it matches `^[A-Za-z0-9][A-Za-z0-9 ._+\-]{0,254}$`; otherwise the finding has no subject.
`no_indices_for_prefix` is reported only when the index queue table exists, i.e. the bundle is installed but its
indices were not created (run `bin/console generic-data-index:update:index`).

**Not available** (subject `indices`)

- `not_readable`: the search engine could not be reached or answered with an unexpected response (for example a
  row without an index name, or an open index without a `green`, `yellow` or `red` health; closed indices need no
  health). No metrics are reported.

**Never returned:** document contents, search hosts and credentials, exception messages.

**Sources:** the search indices of the configured prefix (`_cat/indices`).
