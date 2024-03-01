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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer;

use Pimcore\Bundle\GenericDataIndexBundle\Service\DataObjectTypeSerializationHandler\DataObjectTypeSerializationHandlerInterface;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class DataObjectTypeSerializationHandlerService
{
    public function __construct(
        private readonly ServiceLocator $adapterLocator
    ) {
    }

    public function getSerializationHandler(string $type): ?DataObjectTypeSerializationHandlerInterface
    {
        if ($this->adapterLocator->has($type)) {
            try {
                return $this->adapterLocator->get($type);
            } catch (ContainerExceptionInterface) {
                return null;
            }
        }

        return null;
    }
}
