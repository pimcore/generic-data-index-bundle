<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection;

use Exception;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ClientType;
use Pimcore\Bundle\GenericDataIndexBundle\MessageHandler\DispatchQueueMessagesHandler;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */

/**
 * @internal
 */
class PimcoreGenericDataIndexExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');

        $this->registerIndexServiceParams($container, $config['index_service']);
    }

    /**
     * @throws Exception
     */
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('doctrine_migrations')) {
            $loader = new YamlFileLoader(
                $container,
                new FileLocator(__DIR__ . '/../../config')
            );

            $loader->load('doctrine_migrations.yaml');
        }

        $config = $this->getParsedConfig(__DIR__ . '/../../config/doctrine.yaml');

        $container->prependExtensionConfig('doctrine', $config['doctrine']);

        // Dedicated log channel so Generic Data Index logs can be filtered, raised to debug, or
        // routed separately from the rest of the application. Additive: the channel is still
        // handled by the application's existing handlers, it is only tagged distinctly.
        if ($container->hasExtension('monolog')) {
            $container->prependExtensionConfig('monolog', [
                'channels' => ['pimcore_generic_data_index'],
            ]);
        }
    }

    private function registerIndexServiceParams(ContainerBuilder $container, array $indexSettings): void
    {
        $indexSettings['index_settings'] = $this->getIndexSettings($indexSettings['index_settings']);
        $definition = $container->getDefinition(SearchIndexConfigServiceInterface::class);
        $definition->setArgument('$clientType', $indexSettings['client_params']['client_type']);
        $definition->setArgument('$indexPrefix', $indexSettings['client_params']['index_prefix']);
        $definition->setArgument('$indexSettings', $indexSettings['index_settings']);
        $definition->setArgument('$searchSettings', $indexSettings['search_settings']);
        $definition->setArgument('$systemFieldsSettings', $indexSettings['system_fields_settings']);

        $clientId = $this->getDefaultSearchClientId($indexSettings);
        $container->setAlias('generic-data-index.search-client', $clientId);

        $container->setParameter(
            'generic-data-index.index-prefix',
            $indexSettings['client_params']['index_prefix']
        );

        $definition = $container->getDefinition(DispatchQueueMessagesHandler::class);
        $definition->setArgument('$queueSettings', $indexSettings['queue_settings']);

        $definition = $container->getDefinition(SearchIndexServiceInterface::class);
        if ($definition->getClass() === \Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DefaultSearchService::class) {
            $definition->setArgument('$reindexMaxPolls', $indexSettings['reindex_settings']['max_polls']);
            $definition->setArgument('$reindexPollIntervalSeconds', $indexSettings['reindex_settings']['poll_interval']);
        }
    }

    private function getIndexSettings(array $indexSettings): array
    {
        $defaultIndexSettings = $this->getParsedConfig(
            __DIR__ . '/../../config/pimcore/default_index_settings.yaml'
        );

        return $this->mergeConfig($defaultIndexSettings, $indexSettings);
    }

    private function mergeConfig(array $default, array $custom): array
    {
        foreach ($custom as $key => $value) {
            $default[$key] = is_array($value) && isset($default[$key]) && is_array($default[$key])
                ? $this->mergeConfig($default[$key], $value)
                : $value;
        }

        return $default;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getParsedConfig(string $fileLocation): array
    {
        try {
            return Yaml::parseFile($fileLocation);
        } catch (ParseException $e) {
            throw new InvalidArgumentException(
                sprintf('The file "%s" does not contain valid YAML.', $fileLocation),
                0,
                $e
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getDefaultSearchClientId(array $indexSettings): string
    {
        $clientType = $indexSettings['client_params']['client_type'];
        $clientName = $indexSettings['client_params']['client_name'];

        return match ($clientType) {
            ClientType::OPEN_SEARCH->value => 'pimcore.openSearch.custom_client.' . $clientName,
            ClientType::ELASTIC_SEARCH->value => 'pimcore.elasticsearch.custom_client.' . $clientName,
            default => throw new InvalidArgumentException(
                sprintf('Invalid client type: %s', $indexSettings['client_params']['client_type'])
            )
        };
    }
}
