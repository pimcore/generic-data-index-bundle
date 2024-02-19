<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     PCL
 */


namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter;

use InvalidArgumentException;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Data\Block;

/**
 * @internal
 */
final class BlockAdapter extends AbstractAdapter
{

    public function getOpenSearchMapping(): array
    {
        $fieldDefinitions = $this->getFieldDefinition();
        if(!$fieldDefinitions instanceof Block) {
            throw new InvalidArgumentException('FieldDefinition must be an instance of ' . Block::class);
        }
        /** @var Data[] $items */
        $items = $fieldDefinitions->getChildren();
        $properties = [];

        foreach ($items as $item) {
            $adapter = $this->getFieldDefinitionService()->getFieldDefinitionAdapter($item);
            $properties[$item->getName()] = $adapter->getOpenSearchMapping();
        }
        return [
            'type' => 'nested',
            'properties' => $properties
        ];
    }
}