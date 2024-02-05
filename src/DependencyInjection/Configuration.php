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

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Builder\VariableNodeDefinition;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */

/**
 * @internal
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pimcore_generic_data_index');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        // @phpstan-ignore-next-line
        $rootNode
            ->children()
                ->arrayNode('index_service')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('client_params')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('client_name')
                                    ->info('Name of OpenSearch client from the PimcoreOpenSearchClientBundle to be used.')
                                    ->defaultValue('default')
                                ->end()
                                ->scalarNode('index_prefix')
                                    ->defaultValue('pimcore_')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('search_settings')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('list_page_size')
                                    ->defaultValue(60)
                                ->end()
                                ->scalarNode('list_max_filter_options')
                                    ->defaultValue(500)
                                ->end()
                                ->scalarNode('max_synchronous_children_rename_limit')
                                    ->defaultValue(500)
                                    ->info('Maximum number of direct/synchronous children path updates if asset folders get renamed. If more then the given number of children need an path update the process will be done by the asynchronous index update command. This mechanismn is needed to be able to see directly the new paths in the folder navigation.')
                                ->end()
                                ->arrayNode('search_analyzer_attributes')
                                    ->useAttributeAsKey('type')
                                        ->prototype('scalar')
                                    ->end()
                                    ->arrayPrototype()
                                        ->children()
                                            ->append($this->buildVariableNode('fields'))
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->append($this->buildVariableNode('index_settings'))
                        ->arrayNode('queue_settings')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('worker_count')
                                    ->defaultValue(1)
                                    ->validate()
                                        ->ifTrue(function ($value) {
                                            return $value < 1;
                                        })
                                        ->thenInvalid('Worker count must be at least 1.')
                                    ->end()
                                ->end()
                                ->scalarNode('min_batch_size')
                                    ->defaultValue(5)
                                ->end()
                                ->scalarNode('max_batch_size')
                                    ->defaultValue(400)
                                ->end()
                            ->end()
                        ->end()
                         ->arrayNode('system_fields_settings')
                            ->children()
                                ->append($this->buildSystemFieldsSettingsNode('general'))
                                ->append($this->buildSystemFieldsSettingsNode('data_object'))
                                ->append($this->buildSystemFieldsSettingsNode('asset'))
                            ->end()
                        ->end()
                    ->end()
                ->end();

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        return $treeBuilder;
    }

    private function buildVariableNode(string $name, array $defaultValue = [], string $documentation = null): NodeDefinition
    {
        $node = new VariableNodeDefinition($name);
        if ($documentation) {
            $node->info($documentation);
        }

        $node
            ->defaultValue($defaultValue)
            ->treatNullLike([])
            ->beforeNormalization()
            ->castToArray()
            ->end();

        return $node;
    }

    private function buildSystemFieldsSettingsNode(string $name): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition($name);
        // @phpstan-ignore-next-line
        $node
            ->useAttributeAsKey('field')
                ->prototype('scalar')
            ->end()
            ->arrayPrototype()
                ->children()
                    ->scalarNode('type')
                        ->isRequired()
                    ->end()
                    ->scalarNode('analyzer')
                    ->end()
                    ->append($this->buildVariableNode('properties'))
                    ->append($this->buildVariableNode('fields'))
                ->end()
            ->end();

        return $node;
    }
}
