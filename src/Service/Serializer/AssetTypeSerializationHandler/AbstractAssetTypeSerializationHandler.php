<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\AssetTypeSerializationHandler;

use Pimcore\Model\Asset;

abstract class AbstractAssetTypeSerializationHandler implements AssetTypeSerializationHandlerInterface
{

    public function getAdditionalSystemFields(Asset $asset): array
    {
        return [];
    }
}