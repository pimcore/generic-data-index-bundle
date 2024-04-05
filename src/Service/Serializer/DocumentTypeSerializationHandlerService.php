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

use Pimcore\Bundle\GenericDataIndexBundle\Service\DocumentTypeSerializationHandler\HandlerInterface;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

final readonly class DocumentTypeSerializationHandlerService
{
    public function __construct(
        private ServiceLocator $adapterLocator
    ) {
    }

    public function getSerializationHandler(string $type): ?HandlerInterface
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
