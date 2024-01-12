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

use Pimcore\Model\DataObject\Concrete;

class AdvancedManyToManyObjectRelationAdapter extends ManyToManyObjectRelationAdapter
{
    protected function doGetIndexDataValue(Concrete $object): mixed
    {
        $value = [];
        $objectMetadata = $this->doGetRawIndexDataValue($object);

        if ($objectMetadata) {
            foreach ($objectMetadata as $objectMetadataEntry) {
                $dataObject = Concrete::getById($objectMetadataEntry->getObjectId());
                if ($dataObject) {
                    $value[] = $this->getArrayValuesByElement($dataObject);
                }
            }
        }

        return $value;
    }
}
