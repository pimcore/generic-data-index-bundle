<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer;

use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\AssetTypeSerializationHandler\AssetTypeSerializationHandlerInterface;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class AssetTypeSerializationHandlerService
{
    public function __construct(
        private readonly ServiceLocator $adapterLocator
    ) {
    }

    public function getSerializationHandler(string $type): ?AssetTypeSerializationHandlerInterface
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
