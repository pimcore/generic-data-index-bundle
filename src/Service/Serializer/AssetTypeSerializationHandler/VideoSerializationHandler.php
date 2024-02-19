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

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset\VideoSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResultItem;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\Asset\Video;

class VideoSerializationHandler extends AbstractAssetTypeSerializationHandler
{
    /**
     * @throws Exception
     */
    public function getAdditionalSystemFields(Asset $asset): array
    {
        if(!$asset instanceof Video) {
            return [];
        }

        return [
            VideoSystemField::IMAGE_THUMBNAIL->value => $this->getImageThumbnail($asset),
            VideoSystemField::DURATION->value => $asset->getDuration(),
            VideoSystemField::WIDTH->value => $asset->getWidth(),
            VideoSystemField::HEIGHT->value => $asset->getHeight(),
        ];
    }

    public function createSearchResultModel(array $indexData): AssetSearchResultItem
    {
        return (new AssetSearchResultItem\Video())
            ->setImageThumbnail(VideoSystemField::IMAGE_THUMBNAIL->getData($indexData))
            ->setDuration(VideoSystemField::DURATION->getData($indexData))
            ->setWidth(VideoSystemField::WIDTH->getData($indexData))
            ->setHeight(VideoSystemField::HEIGHT->getData($indexData));
    }

    private function getImageThumbnail(Video $video): string
    {
        return $video->getImageThumbnail(Image\Thumbnail\Config::getPreviewConfig())->getPath();
    }
}
