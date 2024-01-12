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

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Data\ElementMetadata;

class AdvancedManyToManyRelationAdapter extends ManyToManyObjectRelationAdapter
{
    protected function doGetIndexDataValue(Concrete $object): mixed
    {
        $value = [];
        /** @var ElementMetadata[] $elementMetadata */
        $elementMetadata = $this->doGetRawIndexDataValue($object);

        if ($elementMetadata) {
            foreach ($elementMetadata as $elementMetadataEntry) {
                $element = $elementMetadataEntry->getElement();
                if ($element instanceof Concrete || $element instanceof Asset) {
                    $value[] = $this->getArrayValuesByElement($element);
                }
            }
        }

        return $value;
    }
}
