<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\Compiler;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\DependencyInjection\CompilerPassTag;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


class SearchIndexFieldDefinitionPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $definitionList = [
            'pimcore.generic_data_index.object.search_index_field_definition_locator' => CompilerPassTag::DATA_OBJECT_SEARCH_INDEX_FIELD_DEFINITION->value,
            #'pimcore.generic_data_index.asset.search_index_field_definition_locator' => CompilerPassTag::DATA_OBJECT_SEARCH_INDEX_FIELD_DEFINITION->value
        ];

        foreach ($definitionList as $definitionId => $serviceTagName) {
            $taggedServices = $container->findTaggedServiceIds($serviceTagName);

            $arguments = [];
            if (sizeof($taggedServices)) {
                foreach ($taggedServices as $id => $tags) {
                    foreach ($tags as $attributes) {
                        $arguments[$attributes['type']] = new Reference($id);
                    }
                }
            }

            // load mappings for field definition adapters
            $serviceLocator = $container->getDefinition($definitionId);
            $serviceLocator->setArgument(0, $arguments);
        }
    }
}
