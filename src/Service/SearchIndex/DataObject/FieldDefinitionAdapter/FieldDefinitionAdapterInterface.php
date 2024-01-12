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

use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Concrete;

interface FieldDefinitionAdapterInterface
{
    public function setFieldDefinition(Data $fieldDefinition): self;

    public function getFieldDefinition(): Data;

    public function getIndexData(Concrete $object): mixed;

    public function getOpenSearchMapping(): array;
}
