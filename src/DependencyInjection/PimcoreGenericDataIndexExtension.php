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

use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
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
class PimcoreGenericDataIndexExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');

        $this
            ->registerIndexServiceParams($container, $config['index_service']);
    }

    public function prepend(ContainerBuilder $container)
    {
        $yamlParser = new Yaml();
        $filename = __DIR__ . '/../../config/doctrine.yaml';

        try {
            $config = $yamlParser->parseFile($filename);
        } catch (ParseException $e) {
            throw new InvalidArgumentException(sprintf('The file "%s" does not contain valid YAML.', $filename), 0, $e);
        }

        $container->prependExtensionConfig('doctrine', $config['doctrine']);
    }

    protected function registerIndexServiceParams(ContainerBuilder $container, array $esClientParams)
    {
        $definition = $container->getDefinition(SearchIndexConfigService::class);
        $definition->setArgument('$indexPrefix', $esClientParams['es_client_params']['index_prefix']);
        $definition->setArgument('$indexSettings', $esClientParams['index_settings']);
        $definition->setArgument('$searchSettings', $esClientParams['search_settings']);

        return $this;
    }
}
