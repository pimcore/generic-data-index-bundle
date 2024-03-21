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

namespace Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\Compiler;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\DependencyInjection\CompilerPassTag;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
final class ServiceLocatorPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container): void
    {
        $definitionList = [
            'pimcore.generic_data_index.object.search_index_field_definition_locator' =>
                CompilerPassTag::DATA_OBJECT_SEARCH_INDEX_FIELD_DEFINITION->value,
            'pimcore.generic_data_index.asset.search_index_field_definition_locator' =>
                CompilerPassTag::ASSET_SEARCH_INDEX_FIELD_DEFINITION->value,
            'pimcore.generic_data_index.asset.type_serialization_handler_locator' =>
                CompilerPassTag::ASSET_TYPE_SERIALIZATION_HANDLER->value,
            'pimcore.generic_data_index.data_object.type_serialization_handler_locator' =>
                CompilerPassTag::DATA_OBJECT_TYPE_SERIALIZATION_HANDLER->value,
            'pimcore.generic_data_index.asset.mapping_provider_locator' =>
                CompilerPassTag::ASSET_MAPPING_PROVIDER->value,
            'pimcore.generic_data_index.document.type_serialization_handler_locator' =>
                CompilerPassTag::DOCUMENT_TYPE_SERIALIZATION_HANDLER->value,
        ];

        foreach ($definitionList as $definitionId => $serviceTagName) {
            $taggedServices = $container->findTaggedServiceIds($serviceTagName);

            $arguments = [];
            if (count($taggedServices)) {
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
