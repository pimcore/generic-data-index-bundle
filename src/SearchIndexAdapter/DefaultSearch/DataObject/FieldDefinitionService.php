<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DataObject;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DataObject\FieldDefinitionServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Concrete;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
final readonly class FieldDefinitionService implements FieldDefinitionServiceInterface
{
    public function __construct(
        private ServiceLocator $adapterLocator
    ) {
    }

    public function getFieldDefinitionAdapter(Data $fieldDefinition): ?AdapterInterface
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

    public function normalizeValue(?Data $fieldDefinition, mixed $value): mixed
    {
        if ($fieldDefinition === null) {
            return $value;
        }

        if ($adapter = $this->getFieldDefinitionAdapter($fieldDefinition)) {
            return $adapter->normalize($value);
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public function getInheritedFieldData(
        ?Data $fieldDefinition,
        Concrete $dataObject,
        string $key,
        mixed $value,
    ): array {
        if ($fieldDefinition === null) {
            return [];
        }

        $adapter = $this->getFieldDefinitionAdapter($fieldDefinition);
        if ($adapter === null) {
            return [];
        }

        return $adapter->getInheritedData($dataObject, $dataObject->getId(), $value, $key);
    }
}
