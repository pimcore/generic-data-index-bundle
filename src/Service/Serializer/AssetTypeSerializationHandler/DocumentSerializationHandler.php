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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset\DocumentSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResultItem;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Document;
use Pimcore\Model\Asset\Image;

class DocumentSerializationHandler extends AbstractAssetTypeSerializationHandler
{
    /**
     * @throws Exception
     */
    public function getAdditionalSystemFields(Asset $asset): array
    {
        if(!$asset instanceof Document) {
            return [];
        }

        return [
            DocumentSystemField::IMAGE_THUMBNAIL->value => $this->getImageThumbnail($asset),
            DocumentSystemField::PAGE_COUNT->value => $asset->getPageCount(),
            DocumentSystemField::TEXT->value => $asset->getText(),
        ];
    }

    public function createSearchResultModel(array $indexData): AssetSearchResultItem
    {
        return (new AssetSearchResultItem\Document())
            ->setImageThumbnail(DocumentSystemField::IMAGE_THUMBNAIL->getData($indexData))
            ->setPageCount(DocumentSystemField::PAGE_COUNT->getData($indexData));
    }

    private function getImageThumbnail(Document $document): string
    {
        return $document->getImageThumbnail(Image\Thumbnail\Config::getPreviewConfig())->getPath();
    }
}
