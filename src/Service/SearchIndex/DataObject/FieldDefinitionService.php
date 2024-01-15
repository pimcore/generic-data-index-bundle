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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject;

use Pimcore\{
    Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter\FieldDefinitionAdapterInterface,
    Model\DataObject\ClassDefinition};
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class FieldDefinitionService
{
    public function __construct(protected readonly ServiceLocator $adapterLocator)
    {
    }

    public function getFieldDefinitionAdapter(ClassDefinition\Data $fieldDefinition): ?FieldDefinitionAdapterInterface
    {
        $adapter = null;

        if ($this->adapterLocator->has($fieldDefinition->getFieldType())) {
            try {
                $adapter = $this->adapterLocator->get($fieldDefinition->getFieldType());
            } catch (ContainerExceptionInterface) {
                return null;
            }
            $adapter->setFieldDefinition($fieldDefinition);
        }

        return $adapter;
    }
}
