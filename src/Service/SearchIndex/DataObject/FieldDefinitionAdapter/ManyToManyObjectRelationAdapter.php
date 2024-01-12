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

class ManyToManyObjectRelationAdapter extends ManyToManyRelationAdapter
{
    protected function doGetIndexDataValue(Concrete $object): mixed
    {
        $value = [];
        /** @var Concrete[] $dataObjects */
        $dataObjects = $this->doGetRawIndexDataValue($object);

        if ($dataObjects) {
            foreach ($dataObjects as $dataObject) {
                /* @phpstan-ignore-next-line */
                if ($dataObject) {
                    $value[] = $this->getArrayValuesByElement($dataObject);
                }
            }
        }

        return $value;
    }
}
