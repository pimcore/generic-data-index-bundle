<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\AssetTypeSerializationHandler;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResultItem;
use Pimcore\Model\Asset;

interface AssetTypeSerializationHandlerInterface
{
    public function getAdditionalSystemFields(Asset $asset): array;

    /**
     * Type independent attributes will be added in AssetSearchResultDenormalizer
     */
    public function createSearchResultModel(array $indexData): AssetSearchResultItem;
}