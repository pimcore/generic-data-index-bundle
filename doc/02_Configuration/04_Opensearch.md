---
title: OpenSearch Client Setup
description: Configure OpenSearch as the search engine for the Generic Data Index bundle.
---

# OpenSearch Client Setup

:::info

Supported OpenSearch versions: >= 2.7 and < 3.0.
OpenSearch 3 is not supported yet — see the
[Pimcore OpenSearch Client](https://github.com/pimcore/opensearch-client) for the
supported client versions.

:::

Configuration requires two steps:
1. Configure an OpenSearch client via
   [Pimcore OpenSearch Client](https://github.com/pimcore/opensearch-client).
2. Assign the client to the Generic Data Index bundle.

```yaml
# 1. OpenSearch client configuration
pimcore_open_search_client:
    clients:
        default:
            hosts: ['https://opensearch:9200']
            password: 'admin'
            username: 'admin'
            ssl_verification: false

# 2. Assign client to Generic Data Index
pimcore_generic_data_index:
    index_service:
        client_params:
            client_name: default
```

For additional client options, see the
[Pimcore OpenSearch Client documentation](https://github.com/pimcore/opensearch-client/blob/1.x/doc/02_Configuration.md).

## Disable Auto-Index Creation

OpenSearch creates indices automatically when storing data to a nonexistent index.
This causes incorrect indices and missing aliases.
Disable this in your OpenSearch configuration:

```
action.auto_create_index=false
```

See [#165](https://github.com/pimcore/generic-data-index-bundle/issues/165) and
[#202](https://github.com/pimcore/generic-data-index-bundle/issues/202) for details.
