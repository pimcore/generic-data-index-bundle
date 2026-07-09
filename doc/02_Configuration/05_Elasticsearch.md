---
title: Elasticsearch Client Setup
description: Configure Elasticsearch as the search engine for the Generic Data Index bundle.
---

# Elasticsearch Client Setup

:::info

Requires Elasticsearch >= 8.0

:::

Configuration requires two steps:
1. Configure an Elasticsearch client via
   [Pimcore Elasticsearch Client](https://github.com/pimcore/elasticsearch-client).
2. Assign the client to the Generic Data Index bundle with `client_type: 'elasticsearch'`.

```yaml
# 1. Elasticsearch client configuration
pimcore_elasticsearch_client:
    es_clients:
      default:
        hosts: ['elastic:9200']
        username: 'elastic'
        password: 'somethingsecret'
        logger_channel: 'pimcore.elasticsearch'

# 2. Assign client to Generic Data Index (default client_type is 'openSearch')
pimcore_generic_data_index:
    index_service:
        client_params:
            client_name: default
            client_type: 'elasticsearch'
```

For additional client options, see the
[Pimcore Elasticsearch Client documentation](https://github.com/pimcore/elasticsearch-client/blob/1.x/README.md).

## Disable Auto-Index Creation

Elasticsearch creates indices automatically when storing data to a nonexistent index.
This causes incorrect indices and missing aliases.
Disable this in your Elasticsearch configuration:

```
action.auto_create_index=false
```
