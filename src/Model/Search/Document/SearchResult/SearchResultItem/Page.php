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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;

/**
 * @internal
 */
final class Page extends DocumentSearchResultItem
{
    use PageSnippetTrait;

    private ?string $title;

    private ?string $description;

    private ?string $prettyUrl;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): Page
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): Page
    {
        $this->description = $description;

        return $this;
    }

    public function getPrettyUrl(): ?string
    {
        return $this->prettyUrl;
    }

    public function setPrettyUrl(?string $prettyUrl): Page
    {
        $this->prettyUrl = $prettyUrl;

        return $this;
    }
}
