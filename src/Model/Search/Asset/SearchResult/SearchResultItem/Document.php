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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;

class Document extends AssetSearchResultItem
{
    private ?int $pageCount;

    private ?string $imageThumbnail;

    public function getPageCount(): ?int
    {
        return $this->pageCount;
    }

    public function setPageCount(?int $pageCount): Document
    {
        $this->pageCount = $pageCount;

        return $this;
    }

    public function getImageThumbnail(): ?string
    {
        return $this->imageThumbnail;
    }

    public function setImageThumbnail(?string $imageThumbnail): Document
    {
        $this->imageThumbnail = $imageThumbnail;

        return $this;
    }
}
