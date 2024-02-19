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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\AssetTypeSerializationHandler;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset\ImageSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResultItem;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Image;

class ImageSerializationHandler extends AbstractAssetTypeSerializationHandler
{
    public function getAdditionalSystemFields(Asset $asset): array
    {
        if(!$asset instanceof Image) {
            return [];
        }

        return [
            ImageSystemField::THUMBNAIL->value => $this->getThumbnail($asset),
            ImageSystemField::WIDTH->value => $asset->getWidth(),
            ImageSystemField::HEIGHT->value => $asset->getHeight(),
        ];
    }

    public function createSearchResultModel(array $indexData): AssetSearchResultItem
    {
        return (new AssetSearchResultItem\Image())
            ->setThumbnail(ImageSystemField::THUMBNAIL->getData($indexData))
            ->setWidth(ImageSystemField::WIDTH->getData($indexData))
            ->setHeight(ImageSystemField::HEIGHT->getData($indexData));
    }

    private function getThumbnail(Image $image): string
    {
        return $image->getThumbnail(Image\Thumbnail\Config::getPreviewConfig())->getPath();
    }
}
