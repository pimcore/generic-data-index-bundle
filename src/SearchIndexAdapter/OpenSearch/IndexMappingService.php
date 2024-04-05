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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidMappingException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Mapping;
use Pimcore\Model\DataObject\ClassDefinition\Data;

readonly class IndexMappingService implements IndexMappingServiceInterface
{
    public function __construct(
        private FieldDefinitionServiceInterface $fieldDefinitionService
    ) {
    }

    /**
     * @param Data[] $fieldDefinitions
     */
    public function getMappingForFieldDefinitions(array $fieldDefinitions): array
    {
        $mapping['properties'] = [];
        foreach ($fieldDefinitions as $fieldDefinition) {
            if (!$fieldDefinition->getName()) {
                continue;
            }

            try {
                $fieldMapping = $this->getMapping($fieldDefinition);
                $mapping['properties'][$fieldMapping->getMappingName()] = $fieldMapping->getMapping();
            } catch (InvalidMappingException) {
                continue;
            }
        }

        $mapping['properties'] = $this->transformLocalizedfields($mapping['properties']);

        return $mapping;
    }

    /**
     * @throws InvalidMappingException
     */
    private function getMapping(Data $fieldDefinition): Mapping
    {
        $fieldDefinitionAdapter = $this->fieldDefinitionService->getFieldDefinitionAdapter($fieldDefinition);
        if(!$fieldDefinitionAdapter) {
            throw new InvalidMappingException(
                'Invalid field definition adapter for field definition: ' . $fieldDefinition->getName()
            );
        }

        $searchAttributeName =  $fieldDefinitionAdapter->getIndexAttributeName();

        return new Mapping(
            mappingName: $searchAttributeName,
            mapping: $fieldDefinitionAdapter->getIndexMapping()
        );
    }

    private function transformLocalizedfields(array $data): array
    {
        if (isset($data['localizedfields'])) {
            $localizedFields = $data['localizedfields']['properties'];
            unset($data['localizedfields']);

            foreach ($localizedFields as $locale => $attributes) {
                foreach ($attributes['properties'] as $attributeName => $attributeData) {
                    $data[$attributeName] = $data[$attributeName] ?? ['type' => 'object', 'properties' => []];
                    $data[$attributeName]['properties'][$locale] = $attributeData;
                }
            }
        }

        return $data;
    }
}
