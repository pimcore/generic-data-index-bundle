# Installation of Generic Data Index

:::info

 This bundle is only supported on Pimcore Core Framework 11.

:::

 ## Bundle Installation

To install the Generic Data Index bundle, follow the steps below:

1) Install the required dependencies:

```bash
composer require pimcore/generic-data-index-bundle
```

2) Make sure the bundle is enabled in the `config/bundles.php` file. The following lines should be added:
```php
use Pimcore\Bundle\GenericDataIndexBundle\PimcoreGenericDataIndexBundle;
// ...
return [
    // ...
    PimcoreGenericDataIndexBundle::class => ['all' => true],
    // ...
];  
```

3) Install the bundle:

```bash
bin/console pimcore:bundle:install PimcoreGenericDataIndexBundle
```

4) Setup open search client configuration in your Symfony configuration files (e.g. `config.yaml`):

See [OpenSearch Client Setup](../02_Configuration/04_Opensearch.md) for more information.

5) Setup one or multiple Symfony messenger workers for the indexing queue processing. It is recommended to use a tool like Supervisor to manage the workers.
   For more information, see the [Symfony Messenger documentation](https://symfony.com/doc/current/messenger.html). 

```bash
./bin/console messenger:consume pimcore_generic_data_index_queue
```

## Commands after Installation

It is needed to run following command after installation (at least) once to create the indices and add all assets and data objects to the index queue:
```bash
./bin/console generic-data-index:update:index -r
```