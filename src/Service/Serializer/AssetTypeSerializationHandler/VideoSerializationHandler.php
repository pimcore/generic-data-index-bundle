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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\AssetTypeSerializationHandler;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset\VideoSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\Asset\Video;
use Throwable;

class VideoSerializationHandler extends AbstractHandler
{
    use LoggerAwareTrait;

    public function getAdditionalSystemFields(Asset $asset): array
    {
        if (!$asset instanceof Video) {
            return [];
        }

        return [
            VideoSystemField::IMAGE_THUMBNAIL->value => $this->getImageThumbnail($asset),
            VideoSystemField::DURATION->value => $this->getDuration($asset),
            VideoSystemField::WIDTH->value => $this->getWidth($asset),
            VideoSystemField::HEIGHT->value => $this->getHeight($asset),
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
        } catch (Throwable $e) {
            $this->logger->error('Thumbnail generation failed for video asset: ' .
                $video->getId() .
                ' error ' .
                $e->getMessage()
            );
        }

        return null;
    }

    private function getDuration(Video $video): ?float
    {
        try {
            return $video->getDuration();
        } catch (Throwable $e) {
            $this->logger->error('Duration extraction failed for video asset: ' .
                $video->getId() .
                ' error ' .
                $e->getMessage()
            );
        }

        return null;
    }

    private function getWidth(Video $video): ?int
    {
        try {
            return $video->getWidth();
        } catch (Throwable $e) {
            $this->logger->error('Width extraction failed for video asset: ' .
                $video->getId() .
                ' error ' .
                $e->getMessage()
            );
        }

        return null;
    }

    private function getHeight(Video $video): ?int
    {
        try {
            return $video->getHeight();
        } catch (Throwable $e) {
            $this->logger->error('Height extraction failed for video asset: ' .
                $video->getId() .
                ' error ' .
                $e->getMessage()
            );
        }

        return null;
    }
}
