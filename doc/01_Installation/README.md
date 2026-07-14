---
title: Installation
description: Install and configure the Generic Data Index bundle with OpenSearch or Elasticsearch.
---

# Installation

:::info

This bundle requires OpenSearch >= 2.7 and < 3.0 or Elasticsearch >= 8.0.0.
OpenSearch 3 is not supported yet.

:::

## Bundle Installation

1. Install the required dependencies:

```bash
composer require pimcore/generic-data-index-bundle
```

2. Enable the bundle in `config/bundles.php`:

```php
use Pimcore\Bundle\GenericDataIndexBundle\PimcoreGenericDataIndexBundle;
// ...
return [
    // ...
    PimcoreGenericDataIndexBundle::class => ['all' => true],
    // ...
];
```

3. Install the bundle:

```bash
bin/console pimcore:bundle:install PimcoreGenericDataIndexBundle
```

4. Configure the search client in your Symfony configuration (e.g. `config.yaml`).
   See [OpenSearch Client Setup](../02_Configuration/04_Opensearch.md)
   or [Elasticsearch Client Setup](../02_Configuration/05_Elasticsearch.md).

5. Start one or more Symfony Messenger workers for index queue processing.
   Use a process manager like Supervisor to keep workers running.
   See the [Symfony Messenger documentation](https://symfony.com/doc/current/messenger.html)
   for details.

```bash
bin/console messenger:consume pimcore_generic_data_index_queue scheduler_generic_data_index
```

:::tip Deployment hint

For deployments without database access (e.g. CI pipelines), Doctrine ORM cache warm-up
fails because it tries to detect the database version. Configure the server version
explicitly in the default DBAL connection:

```yaml
doctrine:
    dbal:
        default_connection: default
        connections:
            default:
                ...
                server_version: mariadb-10.11.0
```

:::

## Post-Installation

After installation, create the indices and queue all elements for indexing:

```bash
bin/console generic-data-index:update:index -r
```
