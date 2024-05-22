<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\Compiler;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\DependencyInjection\ServiceTag;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameTransformerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\FieldNameValidatorInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\AutowiringFailedException;
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
        $this->handleServiceLocatorDefinitions($container);
        $this->handleTaggedIteratorDefinitions($container);
    }

    private function handleServiceLocatorDefinitions(ContainerBuilder $container): void
    {
        $definitionList = [
            'pimcore.generic_data_index.object.search_index_field_definition_locator' =>
                ServiceTag::DATA_OBJECT_SEARCH_INDEX_FIELD_DEFINITION->value,
            'pimcore.generic_data_index.asset.search_index_field_definition_locator' =>
                ServiceTag::ASSET_SEARCH_INDEX_FIELD_DEFINITION->value,
            'pimcore.generic_data_index.asset.type_serialization_handler_locator' =>
                ServiceTag::ASSET_TYPE_SERIALIZATION_HANDLER->value,
            'pimcore.generic_data_index.data_object.type_serialization_handler_locator' =>
                ServiceTag::DATA_OBJECT_TYPE_SERIALIZATION_HANDLER->value,
            'pimcore.generic_data_index.asset.mapping_provider_locator' =>
                ServiceTag::ASSET_MAPPING_PROVIDER->value,
            'pimcore.generic_data_index.document.type_serialization_handler_locator' =>
                ServiceTag::DOCUMENT_TYPE_SERIALIZATION_HANDLER->value,
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

    private function handleTaggedIteratorDefinitions(ContainerBuilder $container): void
    {
        $definitionList = [
            ServiceTag::PQL_FIELD_NAME_TRANSFORMER->value => FieldNameTransformerInterface::class,
            ServiceTag::PQL_FIELD_NAME_VALIDATOR->value => FieldNameValidatorInterface::class,
        ];

        foreach ($definitionList as $serviceTagName => $interfaceName) {
            foreach ($container->findTaggedServiceIds($serviceTagName) as $taggedServiceId => $tags) {
                $definition = $container->getDefinition($taggedServiceId);
                if (!is_subclass_of($definition->getClass(), $interfaceName)) {
                    throw new AutowiringFailedException(
                        $taggedServiceId,
                        'Service ID ' . $taggedServiceId . ' needs to implement ' . $interfaceName
                    );
                }
            }
        }
    }
}
