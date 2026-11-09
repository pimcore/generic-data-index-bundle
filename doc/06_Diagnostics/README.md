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
| `client_type`                | string      | Configured search client type (`openSearch` or `elasticsearch`).           |
| `client_name`                | string      | Configured search client name.                                             |
| `index_prefix`               | string      | Configured index prefix.                                                   |

The configuration fields are reported only when they look like identifiers (letters, digits, `.`, `_`, `-`);
any other value is reported as `null`.

**Findings** (code `index_queue_backlog`, subject `generic_data_index_queue`)

| Severity | Condition                                       | Message                                                |
|----------|-------------------------------------------------|--------------------------------------------------------|
| warning  | oldest pending entry older than 3600 seconds    | Index queue entries older than one hour are waiting.   |
| critical | oldest pending entry older than 86400 seconds   | Index queue entries older than one day are waiting.    |

Dispatched entries are not counted as backlog: they wait for a messenger worker, not for the dispatcher.

**Not available** (subject `generic_data_index_queue`)

- `table_missing`: the index queue table does not exist (e.g. the bundle's migrations did not run). Only the
  configuration fields are reported.
- `not_readable`: the table could not be read. No metrics are reported.

**Never returned:** element ids, queue payload, search hosts and credentials, exception messages.

**Sources:** `generic_data_index_queue` (one aggregate query, bounded to two seconds on MariaDB and MySQL), the
search index configuration (client type, client name, prefix).
