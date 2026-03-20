# Configuration

The Generic Data Index Bundle indexes and searches Pimcore elements (assets, data objects,
documents) through OpenSearch or Elasticsearch. It provides a central search client
configuration shared across bundles and supports custom search engine integrations.

Configuration covers:

- **Search client** - which OpenSearch or Elasticsearch instance to connect to
- **Index prefix** - namespace for index names to avoid collisions in shared clusters
- **Queue settings** - batch sizes and worker count for asynchronous indexing
- **Index settings** - engine-specific options like result window size and field limits

## Further Reading

- [Index Management](./03_Index_Management.md)
- [OpenSearch Setup](./04_Opensearch.md)
- [Elasticsearch Setup](./05_Elasticsearch.md)
