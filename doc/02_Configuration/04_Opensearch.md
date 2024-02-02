# Open Search setup

Following configuration is required to set up open-search.

```yaml
pimcore_open_search_client:
    clients:
        data_index:
            hosts: ['https://opensearch:9200']
            password: 'admin'
            username: 'admin'
            ssl_verification: false
```