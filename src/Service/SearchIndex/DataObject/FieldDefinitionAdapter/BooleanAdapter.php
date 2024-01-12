<?php

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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Model\DataObject\Concrete;

class BooleanAdapter extends DefaultAdapter
{
    public function getOpenSearchMapping(): array
    {
        return [
            $this->fieldDefinition->getName(),
            [
                'type' => AttributeType::BOOLEAN->value,
            ]
        ];
    }

    public function getIndexData(Concrete $object): mixed
    {
        //Store true/false as string in ES, its interpreted as boolean
        return (bool)$this->doGetRawIndexDataValue($object)
            ? 'true'
            : 'false';
    }
}
