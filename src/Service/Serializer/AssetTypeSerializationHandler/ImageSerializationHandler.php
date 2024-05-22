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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset\ImageSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Image;

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
        } catch (Exception $e) {
            $this->logger->error('Thumbnail generation failed for image asset: ' .
                $image->getId() .
                ' error ' .
                $e->getMessage()
            );
        }

        return null;
    }
}
