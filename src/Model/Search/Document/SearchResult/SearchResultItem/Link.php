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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;

/**
 * @internal
 */
final class Link extends DocumentSearchResultItem
{
    private ?int $internal;

    private ?string $internalType;

    private string $direct;

    private string $linktype;

    private string $href;

    public function getInternal(): ?int
    {
        return $this->internal;
    }

    public function setInternal(?int $internal): Link
    {
        $this->internal = $internal;

        return $this;
    }

    public function getInternalType(): ?string
    {
        return $this->internalType;
    }

    public function setInternalType(?string $internalType): Link
    {
        $this->internalType = $internalType;

        return $this;
    }

    public function getDirect(): string
    {
        return $this->direct;
    }

    public function setDirect(string $direct): Link
    {
        $this->direct = $direct;

        return $this;
    }

    public function getLinktype(): string
    {
        return $this->linktype;
    }

    public function setLinktype(string $linktype): Link
    {
        $this->linktype = $linktype;

        return $this;
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function setHref(string $href): Link
    {
        $this->href = $href;

        return $this;
    }
}
