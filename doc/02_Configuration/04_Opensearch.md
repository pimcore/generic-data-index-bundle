# OpenSearch Client Setup

:::info

This bundle requires minimum version of OpenSearch 2.7.

:::

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

## Important OpenSearch Configuration

OpenSearch automatically creates indices on storing data if the index does not yet exist. This will cause issues with wrong indices and missing aliases. To overcome this issue, you need to disable that feature with the configuration `action.auto_create_index=false`. (see here for more information on this https://github.com/pimcore/generic-data-index-bundle/issues/165 and https://github.com/pimcore/generic-data-index-bundle/issues/202)
