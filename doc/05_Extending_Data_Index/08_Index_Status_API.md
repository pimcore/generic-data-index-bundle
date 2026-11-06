---
title: Index Status API
description: Read the index queue counts and the configured search client from your own code through a read-only, public service.
---

# Index Status API

`IndexQueueStatusServiceInterface` gives monitoring and diagnostics code read-only access to the state of the Generic
Data Index: how many entries the index queue holds and which search client and index prefix the bundle is
configured with. It is public API and covered by the backward compatibility promise; the service behind it is
internal and may change. It is separate from the per-index status of `IndexStatusServiceInterface` (completeness and
rebuild progress of each index), described in [Index Status](../02_Configuration/03_Index_Management.md#index-status).

The service only reads. It does not change the queue, does not connect to OpenSearch or Elasticsearch, and does
not expose hosts, credentials, file paths, element ids or index data.

## Usage

Inject the interface:

```php
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexStatusException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Status\IndexQueueStatusServiceInterface;

final readonly class IndexHealthCheck
{
    public function __construct(private IndexQueueStatusServiceInterface $indexStatusService)
    {
    }

    public function describe(): string
    {
        $client = $this->indexStatusService->getClientConfiguration();

        try {
            $queue = $this->indexStatusService->getQueueStatus();
        } catch (IndexStatusException) {
            return 'The index queue could not be read.';
        }

        return sprintf(
            '%s client "%s", prefix "%s": %d queue entries (%d pending, %d dispatched)',
            $client->getClientType(),
            $client->getClientName(),
            $client->getIndexPrefix(),
            $queue->getTotal(),
            $queue->getPending(),
            $queue->getDispatched()
        );
    }
}
```

## Queue Status

`getQueueStatus()` reads the index queue table (`generic_data_index_queue`) with one aggregate query and returns a
`Pimcore\Bundle\GenericDataIndexBundle\Model\Status\QueueStatus`:

| Method | Meaning |
|--------|---------|
| `getTotal()` | All entries in the queue, pending and dispatched. |
| `getPending()` | Entries waiting to be handed to the messenger transport. |
| `getDispatched()` | Entries handed to the messenger transport and not yet processed. |
| `getOldestPendingOperationTime()` | Operation time of the oldest pending entry as a Unix timestamp in milliseconds, `null` when no entry is pending. |

Treat the counts as backlog signals, not as proof that a worker is missing. A pending count that keeps growing, or an
oldest pending operation time far in the past, means that the queue is not drained fast enough. Dispatched entries
that stay can come from a slow backlog or from failed messages: after a failure they stay until the stale entries
are dispatched again (after 24 hours). Both transports (`scheduler_generic_data_index` and
`pimcore_generic_data_index_queue`) are consumed by workers, and `pimcore_generic_data_index_queue` also carries the
messages that dispatch pending entries. To find the cause, check that both transports have a consumer, run
`bin/console messenger:stats`, look at the failed transport and read the worker logs. See
[Messenger Workers](../02_Configuration/07_Operations_Guide.md#messenger-workers).

The method throws `Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexStatusException` when the queue table cannot
be read, for example while the database is down.

## Client Configuration

`getClientConfiguration()` returns a `Pimcore\Bundle\GenericDataIndexBundle\Model\Status\ClientConfiguration` with
the values of `pimcore_generic_data_index.index_service.client_params`, as configured (environment variables
resolved):

| Method | Configuration key |
|--------|-------------------|
| `getClientType()` | `client_type` (`openSearch` or `elasticsearch`) |
| `getClientName()` | `client_name`, the name of the search client of the OpenSearch or Elasticsearch client bundle |
| `getIndexPrefix()` | `index_prefix` |

It reads the bundle configuration only and never touches the database or the search engine. The connection
settings of the client itself (hosts, user names, passwords, certificates) stay in the client bundle and are not
part of the result.
