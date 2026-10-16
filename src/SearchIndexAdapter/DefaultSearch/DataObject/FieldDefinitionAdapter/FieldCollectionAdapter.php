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
use InvalidArgumentException;
use function is_array;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\ContainerAdapterInterface;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\FieldCollection\DefinitionResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Fieldcollections;
use Pimcore\Model\DataObject\Fieldcollection;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class FieldCollectionAdapter extends AbstractAdapter implements ContainerAdapterInterface
{
    use ContainerFieldsTrait;

    private DefinitionResolverInterface $fieldCollectionDefinition;

    /**
     * @throws Exception
     */
    public function getIndexMapping(): array
    {
        $fieldDefinition = $this->getFieldDefinition();
        if (!$fieldDefinition instanceof Fieldcollections) {
            throw new InvalidArgumentException('FieldDefinition must be of type Fieldcollections');
        }

        $mapping = [];
        $allowedTypes = $fieldDefinition->getAllowedTypes();

        foreach ($allowedTypes as $allowedType) {
            $fieldCollectionDefinition = $this->fieldCollectionDefinition->getByKey($allowedType);
            if (!$fieldCollectionDefinition) {
                continue;
            }
            $mapping[$allowedType] = [
                'type' => AttributeType::NESTED,
                'properties' => $this->getContainerFieldsMapping(
                    $fieldCollectionDefinition->getFieldDefinitions(),
                    static fn (Data $fieldDefinition) => $fieldDefinition->getName()
                ),
            ];
        }

        return [
                'type' => AttributeType::NESTED,
                'properties' => $mapping,
            ];
    }

    public function normalize(mixed $value): ?array
    {
        if (!$value instanceof Fieldcollection) {
            return null;
        }

        $resultItems = [];
        $items = $value->getItems();

        foreach ($items as $item) {
            $type = $item->getType();
            $fieldCollectionDefinition = $this->fieldCollectionDefinition->getByKey($type);
            if (!$fieldCollectionDefinition) {
                continue;
            }
            $resultItem = [];

            foreach ($fieldCollectionDefinition->getFieldDefinitions() as $fieldDefinition) {
                $getter = 'get' . ucfirst($fieldDefinition->getName());
                $resultItem[$type][$fieldDefinition->getName()] = $this->normalizeContainerField(
                    $fieldDefinition,
                    $item->$getter()
                );
            }

            $resultItems[] = $resultItem;
        }

        return $resultItems;
    }

    public function flattenLocalizedFields(mixed $normalizedValue): mixed
    {
        if (!is_array($normalizedValue)) {
            return $normalizedValue;
        }

        foreach ($normalizedValue as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach ($item as $type => $fields) {
                $definition = $this->fieldCollectionDefinition->getByKey((string) $type);
                if ($definition !== null && is_array($fields)) {
                    $normalizedValue[$index][$type] = $this->flattenFields($fields, $definition->getFieldDefinitions());
                }
            }
        }

        return $normalizedValue;
    }

    #[Required]
    public function setFieldCollectionDefinition(DefinitionResolverInterface $definitionResolver): void
    {
        $this->fieldCollectionDefinition = $definitionResolver;
    }
}
