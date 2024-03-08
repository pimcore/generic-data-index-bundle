# OpenSearch Client Setup

Following configuration is required to set up OpenSearch. The OpenSearch client configuration takes place via [Pimcore Opensearch Client](https://github.com/pimcore/opensearch-client) and has two parts:
1) Configuring an OpenSearch client.
2) Define the client to be used by Generic Data Index bundle.

```yaml
# Configuring an OpenSearch client
pimcore_open_search_client:
    clients:
        default:
            hosts: ['https://opensearch:9200']
            password: 'admin'
            username: 'admin'
            ssl_verification: false

# Define the client to be used by your bundle
pimcore_generic_data_index:
    index_service:
        client_params:
            client_name: default
```

For the further configuration of the client, please refer to the [Pimcore OpenSearch Client documentation](https://github.com/pimcore/opensearch-client/blob/1.x/doc/02_Configuration.md).