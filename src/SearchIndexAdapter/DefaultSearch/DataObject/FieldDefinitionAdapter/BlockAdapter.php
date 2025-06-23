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
use Pimcore\Model\DataObject\ClassDefinition\Data\Block;
use Pimcore\Model\DataObject\Data\BlockElement;

/**
 * @internal
 */
final class BlockAdapter extends AbstractAdapter
{
    public function getIndexMapping(): array
    {
        $fieldDefinition = $this->getFieldDefinition();
        if (!$fieldDefinition instanceof Block) {
            throw new InvalidArgumentException('FieldDefinition must be an instance of ' . Block::class);
        }

        $items = $fieldDefinition->getFieldDefinitions();
        $properties = [];
        foreach ($items as $item) {
            $adapter = $this->getFieldDefinitionService()->getFieldDefinitionAdapter($item);
            if ($adapter) {
                $properties[$item->getName()] = $adapter->getIndexMapping();
            }
        }

        return [
            'type' => 'nested',
            'properties' => $properties,
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
                $blockDefinition = $fieldDefinitions[$key];
                $resultItem[$key] = $this->fieldDefinitionService->normalizeValue(
                    $blockDefinition,
                    $fieldValue->getData()
                );
            }

            $resultItems[] = $resultItem;
        }

        return $resultItems;
    }
}
