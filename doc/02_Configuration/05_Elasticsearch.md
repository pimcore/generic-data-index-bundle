# Elasticsearch Client Setup

:::info

This bundle requires minimum version of Elasticsearch 8.0.

:::

Following configuration is required to set up Elasticsearch. The Elasticsearch client configuration takes place via [Pimcore Elasticsearch Client](https://github.com/pimcore/elasticsearch-client) and has two parts:
1) Configuring an Elasticsearch client.
2) Define the client to be used by Generic Data Index bundle.

```yaml
# Configuring an Elasticsearch client
pimcore_elasticsearch_client:
    es_clients:
      default:
        hosts: ['elastic:9200']
        username: 'elastic'
        password: 'somethingsecret'
        logger_channel: 'pimcore.elasicsearch'

# Define the client to be used by your bundle (default client_type is 'openSearch')
pimcore_generic_data_index:
    index_service:
        client_params:
            client_name: default
            client_type: 'elasticsearch'
```

For the further configuration of the client, please refer to the [Pimcore Elasticsearch Client documentation](https://github.com/pimcore/elasticsearch-client/blob/1.x/README.md).

## Important Elasticsearch Configuration

Elasticsearch automatically creates indices on storing data if the index does not yet exist. This will cause issues with wrong indices and missing aliases. To overcome this issue, you need to disable that feature with the configuration `action.auto_create_index=false`.