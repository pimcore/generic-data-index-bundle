---
title: Installation
description: Install and configure the Generic Data Index bundle with OpenSearch or Elasticsearch.
---

# Installation

:::info

This bundle requires OpenSearch >= 2.7 and < 3.0 or Elasticsearch >= 8.12.2 (< 9).
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
   Use a process manager like Supervisor to keep workers running and to restart them:
   saving a class definition or the system settings stops all messenger workers.
   See the [Symfony Messenger documentation](https://symfony.com/doc/current/messenger.html)
   for details, and the [Operations Guide](../02_Configuration/07_Operations_Guide.md#messenger-workers)
   for the worker setup with large indices.

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

On an existing database with many elements, the messenger workers need hours to fill the indices; see
[Capacity Planning](../02_Configuration/07_Operations_Guide.md#capacity-planning), or import an
[index snapshot](../02_Configuration/06_Index_Snapshots.md) instead.
