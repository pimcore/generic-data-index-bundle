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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\AttributeType;
use Pimcore\Bundle\StaticResolverBundle\Models\DataObject\FieldCollection\DefinitionResolverInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\Fieldcollections;
use Pimcore\Model\DataObject\Fieldcollection;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
final class FieldCollectionAdapter extends AbstractAdapter
{
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
                'properties' => [],
            ];
            foreach ($fieldCollectionDefinition->getFieldDefinitions() as $fieldDefinition) {
                $fieldDefinitionAdapter = $this->getFieldDefinitionService()
                    ->getFieldDefinitionAdapter($fieldDefinition);
                if ($fieldDefinitionAdapter) {
                    $mapping[$allowedType]['properties'][$fieldDefinition->getName()] =
                        $fieldDefinitionAdapter->getIndexMapping();
                }
            }
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
                $value = $item->$getter();
                $resultItem[$type][$fieldDefinition->getName()] =
                    $this->fieldDefinitionService->normalizeValue(
                        $fieldDefinition,
                        $value
                    );
            }

            $resultItems[] = $resultItem;
        }

        return $resultItems;
    }

    #[Required]
    public function setFieldCollectionDefinition(DefinitionResolverInterface $definitionResolver): void
    {
        $this->fieldCollectionDefinition = $definitionResolver;
    }
}
