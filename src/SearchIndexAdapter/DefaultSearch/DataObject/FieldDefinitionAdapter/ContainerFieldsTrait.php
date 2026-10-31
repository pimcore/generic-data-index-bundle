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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject\FieldDefinitionAdapter;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\ContainerAdapterInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;

/**
 * Fields of a container (object brick, field collection, block). In the index, the attributes of the container's
 * localized fields are next to its other fields as <attribute>.<language>, without a "localizedfields" level, as on
 * the class level. This matches the keys Studio filters and sorts by (<field>.<brick>.<attribute>.<language>) and
 * the keys of the inherited values. normalize() keeps the "localizedfields" key, as the class level does; the
 * document gets the flat structure through ContainerAdapterInterface::flattenLocalizedFields().
 *
 * @internal
 */
trait ContainerFieldsTrait
{
    /**
     * @param Data[] $fieldDefinitions
     * @param callable(Data, AdapterInterface): string $mappingKey
     */
    private function getContainerFieldsMapping(array $fieldDefinitions, callable $mappingKey): array
    {
        $mapping = [];
        $localizedMapping = [];
        foreach ($fieldDefinitions as $fieldDefinition) {
            $adapter = $this->getFieldDefinitionService()->getFieldDefinitionAdapter($fieldDefinition);
            if (!$adapter) {
                continue;
            }

            if ($adapter instanceof LocalizedFieldsAdapter) {
                $localizedMapping = $adapter->getIndexMapping()['properties'];

                continue;
            }

            $mapping[$mappingKey($fieldDefinition, $adapter)] = $adapter->getIndexMapping();
        }

        // Pimcore does not allow the same name twice; the container's own field would win, as in flattenFields()
        return array_merge($localizedMapping, $mapping);
    }

    /**
     * Normalized value of one container field. Localized fields never use the class-level localized query table.
     *
     * @throws Exception
     */
    private function normalizeContainerField(?Data $fieldDefinition, mixed $value): mixed
    {
        if ($fieldDefinition instanceof Data\Localizedfields) {
            $adapter = $this->getFieldDefinitionService()->getFieldDefinitionAdapter($fieldDefinition);
            if ($adapter instanceof LocalizedFieldsAdapter) {
                return $adapter->normalizeInContainer($value);
            }
        }

        return $this->fieldDefinitionService->normalizeValue($fieldDefinition, $value);
    }

    /**
     * Index document structure of one container item: the localized attributes move up next to the other fields
     * (the item's own fields win), nested containers are flattened as well.
     *
     * @param array<string, mixed> $fields normalized fields of the item
     * @param Data[] $fieldDefinitions the item's field definitions
     *
     * @return array<string, mixed>
     */
    private function flattenFields(array $fields, array $fieldDefinitions): array
    {
        foreach ($fieldDefinitions as $fieldDefinition) {
            $name = $fieldDefinition->getName();
            if (!array_key_exists($name, $fields)) {
                continue;
            }

            $adapter = $this->getFieldDefinitionService()->getFieldDefinitionAdapter($fieldDefinition);
            // the same condition as getContainerFieldsMapping(), so document and mapping agree
            if ($adapter instanceof LocalizedFieldsAdapter) {
                $localized = $fields[$name];
                unset($fields[$name]);
                if (is_array($localized)) {
                    $fields = array_merge($localized, $fields);
                }

                continue;
            }

            if ($adapter instanceof ContainerAdapterInterface) {
                $fields[$name] = $adapter->flattenLocalizedFields($fields[$name]);
            }
        }

        return $fields;
    }
}
