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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Data\InputQuantityValue;
use Pimcore\Model\DataObject\Data\QuantityValue;

class QuantityValueAdapter extends NumericAdapter
{
    public function getOpenSearchMapping(): array
    {
        return [
            $this->fieldDefinition->getName(),
            [
                'properties' => [
                    'value' => [
                        'type' => AttributeType::FLOAT->value,
                    ],
                    'unitAbbrevation' => [
                        'type' => AttributeType::TEXT->value,
                    ],
                ],

            ],
        ];
    }

    protected function doGetIndexDataValue(Concrete $object): mixed
    {
        $data = $this->doGetRawIndexDataValue($object);

        if ($data instanceof InputQuantityValue || $data instanceof QuantityValue) {
            return [
                'value' => (float)$data->getValue(),
                'unitAbbreviation' => $data->getUnit() ? trim($data->getUnit()->getAbbreviation()) : '',
            ];
        }

        return [];
    }
}
