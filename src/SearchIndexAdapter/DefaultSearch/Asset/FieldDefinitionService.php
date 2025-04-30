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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\AdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Asset\FieldDefinitionServiceInterface;
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
