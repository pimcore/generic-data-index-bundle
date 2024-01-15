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

use Carbon\Carbon;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\AttributeType;
use Pimcore\Model\DataObject\Concrete;

class DateAdapter extends DefaultAdapter
{
    public function getOpenSearchMapping(): array
    {
        return [
            $this->fieldDefinition->getName(),
            [
                'type' => AttributeType::DATE->value,
            ],
        ];
    }

    protected function doGetIndexDataValue(Concrete $object): mixed
    {
        /** @var string $value */
        $value = null;
        /** @var Carbon $carbonDate */
        $carbonDate = $this->doGetRawIndexDataValue($object);

        if ($carbonDate instanceof Carbon) {
            $value = $carbonDate->format(\DateTimeInterface::ATOM);
        }

        return $value;
    }
}
