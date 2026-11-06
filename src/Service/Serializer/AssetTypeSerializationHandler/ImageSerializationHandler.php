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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset\ImageSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Image;
use Throwable;

class ImageSerializationHandler extends AbstractHandler
{
    use LoggerAwareTrait;

    public function getAdditionalSystemFields(Asset $asset): array
    {
        if (!$asset instanceof Image) {
            return [];
        }

        return [
            ImageSystemField::THUMBNAIL->value => $this->getThumbnail($asset),
            ImageSystemField::WIDTH->value => $this->getWidth($asset),
            ImageSystemField::HEIGHT->value => $this->getHeight($asset),
        ];
    }

    public function createSearchResultModel(array $indexData): AssetSearchResultItem
    {
        // Width/height stay non-nullable on the model for BC; fall back to 0 when extraction
        // failed and the indexed value is null instead of widening the public setter signature.
        $image = (new SearchResultItem\Image())
            ->setWidth(ImageSystemField::WIDTH->getData($indexData) ?? 0)
            ->setHeight(ImageSystemField::HEIGHT->getData($indexData) ?? 0);

        // The thumbnail is indexed as null when its generation failed. Same BC reasoning as above:
        // keep setThumbnail(string) and leave the (nullable, null by default) property unset.
        $thumbnail = ImageSystemField::THUMBNAIL->getData($indexData);
        if ($thumbnail !== null) {
            $image->setThumbnail($thumbnail);
        }

        return $image;
    }

    private function getThumbnail(Image $image): ?string
    {
        try {
            return $image->getThumbnail(Image\Thumbnail\Config::getPreviewConfig())->getPath();
        } catch (Throwable $e) {
            $this->logger->error('Thumbnail generation failed for image asset: ' .
                $image->getId() .
                ' error ' .
                $e->getMessage()
            );
        }

        return null;
    }

    private function getWidth(Image $image): ?int
    {
        try {
            return $image->getWidth();
        } catch (Throwable $e) {
            $this->logger->error('Width extraction failed for image asset: ' .
                $image->getId() .
                ' error ' .
                $e->getMessage()
            );
        }

        return null;
    }

    private function getHeight(Image $image): ?int
    {
        try {
            return $image->getHeight();
        } catch (Throwable $e) {
            $this->logger->error('Height extraction failed for image asset: ' .
                $image->getId() .
                ' error ' .
                $e->getMessage()
            );
        }

        return null;
    }
}
