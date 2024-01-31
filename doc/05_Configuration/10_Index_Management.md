# Index Queue options

The indexing queue considers the following options:

- **worker_count** (default 1): number of messenger workers to process the queue
- **min_batch_size** (default 5): minimum number of items to process in one batch (when using multiple workers) 
- **max_batch_size** (default 400): maximum number of items to process in one batch

Sample configuration:

```yaml
pimcore_generic_data_index:
    index_service:
        queue_settings:
            worker_count: 1
            min_batch_size: 5
            max_batch_size: 400
```