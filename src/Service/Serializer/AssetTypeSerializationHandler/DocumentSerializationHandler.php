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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset\DocumentSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Document;
use Pimcore\Model\Asset\Image;

class DocumentSerializationHandler extends AbstractHandler
{
    use LoggerAwareTrait;
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
        return (new SearchResultItem\Document())
            ->setImageThumbnail(DocumentSystemField::IMAGE_THUMBNAIL->getData($indexData))
            ->setPageCount(DocumentSystemField::PAGE_COUNT->getData($indexData));
    }

    private function getImageThumbnail(Document $document): ?string
    {
        try {
            return $document->getImageThumbnail(Image\Thumbnail\Config::getPreviewConfig())->getPath();
        } catch (Exception $e) {
            $this->logger->error('Thumbnail generation failed for document asset: ' .
                $document->getId() .
                ' error ' .
                $e->getMessage()
            );
        }

        return null;
    }
}
