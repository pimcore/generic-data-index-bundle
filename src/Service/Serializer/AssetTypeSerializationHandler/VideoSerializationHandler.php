<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\AssetTypeSerializationHandler;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset\VideoSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\Asset\Video;

class VideoSerializationHandler extends AbstractHandler
{
    use LoggerAwareTrait;

    /**
     * @throws Exception
     */
    public function getAdditionalSystemFields(Asset $asset): array
    {
        if (!$asset instanceof Video) {
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
        return (new SearchResultItem\Video())
            ->setImageThumbnail(VideoSystemField::IMAGE_THUMBNAIL->getData($indexData))
            ->setDuration(VideoSystemField::DURATION->getData($indexData))
            ->setWidth(VideoSystemField::WIDTH->getData($indexData))
            ->setHeight(VideoSystemField::HEIGHT->getData($indexData));
    }

    private function getImageThumbnail(Video $video): ?string
    {
        try {
            return $video->getImageThumbnail(Image\Thumbnail\Config::getPreviewConfig())->getPath();
        } catch (Exception $e) {
            $this->logger->error('Thumbnail generation failed for video asset: ' .
                $video->getId() .
                ' error ' .
                $e->getMessage()
            );
        }

        return null;
    }
}
