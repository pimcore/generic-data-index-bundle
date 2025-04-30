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
            ImageSystemField::WIDTH->value => $asset->getWidth(),
            ImageSystemField::HEIGHT->value => $asset->getHeight(),
        ];
    }

    public function createSearchResultModel(array $indexData): AssetSearchResultItem
    {
        return (new SearchResultItem\Image())
            ->setThumbnail(ImageSystemField::THUMBNAIL->getData($indexData))
            ->setWidth(ImageSystemField::WIDTH->getData($indexData))
            ->setHeight(ImageSystemField::HEIGHT->getData($indexData));
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
}
