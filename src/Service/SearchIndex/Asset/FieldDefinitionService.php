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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Asset\FieldDefinitionAdapter\AdapterInterface;
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

    public function getFieldDefinitionAdapter(string $type): ?AdapterInterface
    {
        $adapter = null;

        if ($this->adapterLocator->has($type)) {
            try {
                $adapter = $this->adapterLocator->get($type);
            } catch (ContainerExceptionInterface) {
                return null;
            }
            $adapter->setType($type);
        }

        return $adapter;
    }

    public function normalizeValue(string $type, mixed $value): mixed
    {
        if ($adapter = $this->getFieldDefinitionAdapter($type)) {
            return $adapter->normalize($value);
        }

        return $value;
    }
}
