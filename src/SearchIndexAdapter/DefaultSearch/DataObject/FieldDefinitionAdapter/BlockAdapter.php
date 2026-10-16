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

use InvalidArgumentException;
use function is_array;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\ContainerAdapterInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Block;
use Pimcore\Model\DataObject\Data\BlockElement;

/**
 * @internal
 */
final class BlockAdapter extends AbstractAdapter implements ContainerAdapterInterface
{
    use ContainerFieldsTrait;

    public function getIndexMapping(): array
    {
        $fieldDefinition = $this->getFieldDefinition();
        if (!$fieldDefinition instanceof Block) {
            throw new InvalidArgumentException('FieldDefinition must be an instance of ' . Block::class);
        }

        return [
            'type' => 'nested',
            'properties' => $this->getContainerFieldsMapping(
                $fieldDefinition->getFieldDefinitions(),
                static fn (Data $item) => $item->getName()
            ),
        ];
    }

    public function normalize(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $resultItems = [];
        $fieldDefinition = $this->getFieldDefinition();
        if (!$fieldDefinition instanceof Block) {
            throw new InvalidArgumentException('FieldDefinition must be an instance of ' . Block::class);
        }
        $fieldDefinitions = $fieldDefinition->getFieldDefinitions();
        foreach ($value as $block) {
            $resultItem = [];

            /** @var BlockElement $fieldValue */
            foreach ($block as $key => $fieldValue) {
                $resultItem[$key] = $this->normalizeContainerField(
                    $fieldDefinitions[$key],
                    $fieldValue->getData()
                );
            }

            $resultItems[] = $resultItem;
        }

        return $resultItems;
    }

    public function flattenLocalizedFields(mixed $normalizedValue): mixed
    {
        $fieldDefinition = $this->getFieldDefinition();
        if (!is_array($normalizedValue) || !$fieldDefinition instanceof Block) {
            return $normalizedValue;
        }

        // read once: a nested block uses the same adapter instance and changes its field definition
        $fieldDefinitions = $fieldDefinition->getFieldDefinitions();
        foreach ($normalizedValue as $index => $item) {
            if (is_array($item)) {
                $normalizedValue[$index] = $this->flattenFields($item, $fieldDefinitions);
            }
        }

        return $normalizedValue;
    }
}
