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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField\Asset\DocumentSystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Document;
use Pimcore\Model\Asset\Image;
use Throwable;

class DocumentSerializationHandler extends AbstractHandler
{
    use LoggerAwareTrait;

    public function getAdditionalSystemFields(Asset $asset): array
    {
        if (!$asset instanceof Document) {
            return [];
        }

        return [
            DocumentSystemField::IMAGE_THUMBNAIL->value => $this->getImageThumbnail($asset),
            DocumentSystemField::PAGE_COUNT->value => $this->getPageCount($asset),
            DocumentSystemField::TEXT->value => $this->getDocumentText($asset),
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
        } catch (Throwable $e) {
            $this->logger->error('Thumbnail generation failed for document asset: ' .
                $document->getId() .
                ' error ' .
                $e->getMessage()
            );
        }

        return null;
    }

    private function getPageCount(Document $document): ?int
    {
        try {
            return $document->getPageCount();
        } catch (Throwable $e) {
            $this->logger->error('Page count extraction failed for document asset: ' .
                $document->getId() .
                ' error ' .
                $e->getMessage()
            );
        }

        return null;
    }

    private function getDocumentText(Document $document): ?string
    {
        try {
            return $document->getText();
        } catch (Throwable $e) {
            $this->logger->error('Text extraction failed for document asset: ' .
                $document->getId() .
                ' error ' .
                $e->getMessage()
            );
        }

        return null;
    }
}
