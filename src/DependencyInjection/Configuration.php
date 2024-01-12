<?php

namespace Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Builder\VariableNodeDefinition;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pimcore_generic_data_index');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('index_service')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('es_client_params')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('es_client_name')
                                    ->info('Name of elasticsearch client configuration to be used. All services tagged with `portal-engine.elasticsearch.es-client-name` will get client injected via `$esClient` constructor argument via compiler pass.')
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
}
