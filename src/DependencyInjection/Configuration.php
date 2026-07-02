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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ClientType;
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
                                    ->info('Name of search client from to be used.')
                                    ->defaultValue('default')
                                ->end()
                                ->scalarNode('client_type')
                                    ->info(sprintf(
                                        'Type of search client to be used. Allowed values: %s. Supports env vars.',
                                        implode(', ', array_column(ClientType::cases(), 'value'))
                                    ))
                                    ->defaultValue(ClientType::OPEN_SEARCH->value)
                                    ->validate()
                                        ->ifTrue(static function (mixed $value): bool {
                                            // Symfony's Config component already short-circuits env-var placeholders
                                            // before calling custom validators, so no explicit env-var guard is needed.
                                            // Non-strings (e.g. null) and empty strings are left to Symfony's own
                                            // type handling and to runtime validation in SearchClientFactory.
                                            if (!is_string($value) || $value === '') {
                                                return false;
                                            }

                                            return !in_array($value, array_column(ClientType::cases(), 'value'), true);
                                        })
                                        ->thenInvalid(sprintf(
                                            'Invalid client_type %%s. Allowed values: %s.',
                                            implode(', ', array_column(ClientType::cases(), 'value'))
                                        ))
                                    ->end()
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
                        ->arrayNode('reindex_settings')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->integerNode('max_polls')
                                    ->min(1)
                                    ->defaultValue(720)
                                    ->info('Maximum number of polling attempts when waiting for an async reindex task (default: 720 = 1 hour at 5-second intervals).')
                                ->end()
                                ->integerNode('poll_interval')
                                    ->min(1)
                                    ->defaultValue(5)
                                    ->info('Seconds to wait between reindex task status polls.')
                                ->end()
                            ->end()
                        ->end()
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
                                ->append($this->buildSystemFieldsSettingsNode('document'))
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

    private function buildVariableNode(
        string $name,
        array $defaultValue = [],
        ?string $documentation = null
    ): NodeDefinition {
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
                    ->scalarNode('ignore_above')
                    ->end()
                    ->append($this->buildVariableNode('properties'))
                    ->append($this->buildVariableNode('fields'))
                ->end()
            ->end();

        return $node;
    }
}
