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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter;

use InvalidArgumentException;
use Pimcore\Model\DataObject\ClassDefinition\Data\Block;

/**
 * @internal
 */
final class BlockAdapter extends AbstractAdapter
{
    public function getOpenSearchMapping(): array
    {
        $fieldDefinition = $this->getFieldDefinition();
        if(!$fieldDefinition instanceof Block) {
            throw new InvalidArgumentException('FieldDefinition must be an instance of ' . Block::class);
        }

        $items = $fieldDefinition->getFieldDefinitions();
        $properties = [];
        foreach ($items as $item) {
            $adapter = $this->getFieldDefinitionService()->getFieldDefinitionAdapter($item);
            if ($adapter) {
                $properties[$item->getName()] = $adapter->getOpenSearchMapping();
            }
        }

        return [
            'type' => 'nested',
            'properties' => $properties,
        ];
    }
}
