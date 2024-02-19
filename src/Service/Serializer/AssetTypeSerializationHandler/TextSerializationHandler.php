<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\AssetTypeSerializationHandler;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResultItem;

class TextSerializationHandler extends AbstractAssetTypeSerializationHandler
{
    public function createSearchResultModel(array $indexData): AssetSearchResultItem
    {
        return new AssetSearchResultItem\Text();
    }
}