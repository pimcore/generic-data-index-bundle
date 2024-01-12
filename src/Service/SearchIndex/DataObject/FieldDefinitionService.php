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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject;

use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter\FieldDefinitionAdapterInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\DependencyInjection\ServiceLocator;

class FieldDefinitionService
{
    public function __construct(protected ServiceLocator $adapterLocator)
    {
    }

    public function getFieldDefinitionAdapter(ClassDefinition\Data $fieldDefinition): ?FieldDefinitionAdapterInterface
    {
        $adapter = null;

        if ($this->adapterLocator->has($fieldDefinition->getFieldtype())) {
            $adapter = $this->adapterLocator->get($fieldDefinition->getFieldtype());
            $adapter->setFieldDefinition($fieldDefinition);
        }

        return $adapter;
    }
}
