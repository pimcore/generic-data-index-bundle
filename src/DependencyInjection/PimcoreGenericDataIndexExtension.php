<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection;

use Exception;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\Factory\OpenSearchClientFactory;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
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

    public function prepend(ContainerBuilder $container): void
    {
        $filename = __DIR__ . '/../../config/doctrine.yaml';

        try {
            $config = Yaml::parseFile($filename);
        } catch (ParseException $e) {
            throw new InvalidArgumentException(sprintf('The file "%s" does not contain valid YAML.', $filename), 0, $e);
        }

        $container->prependExtensionConfig('doctrine', $config['doctrine']);
    }

    private function registerIndexServiceParams(ContainerBuilder $container, array $indexSettings): void
    {
        $definition = $container->getDefinition(SearchIndexConfigServiceInterface::class);
        $definition->setArgument('$indexPrefix', $indexSettings['client_params']['index_prefix']);
        $definition->setArgument('$indexSettings', $indexSettings['index_settings']);
        $definition->setArgument('$searchSettings', $indexSettings['search_settings']);
        $definition->setArgument('$systemFieldsSettings', $indexSettings['system_fields_settings']);

        $definition = $container->getDefinition(OpenSearchClientFactory::class);
        $definition->setArgument('$hosts', $indexSettings['client_params']['hosts']);
        $definition->setArgument('$username', $indexSettings['client_params']['username']);
        $definition->setArgument('$password', $indexSettings['client_params']['password']);
        $definition->setArgument('$sslVerification', $indexSettings['client_params']['ssl_verification']);
    }
}
