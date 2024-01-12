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

class NumericAdapter extends DefaultAdapter
{
    public function getOpenSearchMapping(): array
    {
        return [
            $this->fieldDefinition->getName(),
            [
                'type' => AttributeType::FLOAT->value,
            ],
        ];
    }

    public function getIndexData(Concrete $object): mixed
    {
        return $this->doGetIndexDataValue($object);
    }

    protected function doGetIndexDataValue(Concrete $object): mixed
    {
        return (float)$this->doGetRawIndexDataValue($object);
    }
}
