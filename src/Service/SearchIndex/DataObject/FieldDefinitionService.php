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

use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\DataObject\FieldDefinitionAdapter\AdapterInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Normalizer\NormalizerInterface;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
final class FieldDefinitionService implements FieldDefinitionServiceInterface
{
    public function __construct(
        private readonly ServiceLocator $adapterLocator
    ) {
    }

    public function getFieldDefinitionAdapter(ClassDefinition\Data $fieldDefinition): ?AdapterInterface
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

    public function normalizeValue(?ClassDefinition\Data $fieldDefinition, mixed $value): mixed
    {
        if ($fieldDefinition === null) {
            return $value;
        }

        if ($adapter = $this->getFieldDefinitionAdapter($fieldDefinition)) {
            return $adapter->normalize($value);
        }

        if($fieldDefinition instanceof NormalizerInterface) {
            return $fieldDefinition->normalize($value);
        }

        return $value;
    }
}
