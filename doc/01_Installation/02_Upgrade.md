# Upgrade Information

Following steps are necessary during updating to newer versions.

## Upgrade to 1.3.0
- [Indexing] Added support for Elasticsearch in parallel to Opensearch. Opensearch remains the default search technology. If you are using Elasticsearch, you need to update your symfony configuration as follows:
```yml 
pimcore_generic_data_index:
  index_service:
    client_params:
      client_name: default
      client_type: 'elasticsearch'
```
- [Indexing] Introduced new service alias `generic-data-index.search-client`. This will replace deprecated alias `generic-data-index.opensearch-client` which will be removed in the next major version. 
The new service alias can be used to inject the search client into your services. This search client is an instance of `Pimcore\SearchClient\SearchClientInterface` which is a common interface for OpenSearch and Elasticsearch clients.
- Classes under OpenSearch namespaces are now deprecated and will be removed in the next major version. Please use the classes under the DefaultSearch namespace instead.
- Execute the following command to reindex all elements to be able to use all new features or when switching between OpenSearch and Elasticsearch:

  ```bin/console generic-data-index:update:index```

## Upgrade to 1.1.0
- Execute the following command to reindex all elements to be able to use all new features:

  ```bin/console generic-data-index:update:index```
