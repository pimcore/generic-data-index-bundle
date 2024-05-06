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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;

/**
 * @internal
 */
final class HardLink extends DocumentSearchResultItem
{
    private ?int $sourceId;

    private bool $propertiesFromSource;

    private bool $childrenFromSource;

    public function getSourceId(): ?int
    {
        return $this->sourceId;
    }

    public function setSourceId(?int $sourceId): HardLink
    {
        $this->sourceId = $sourceId;

        return $this;
    }

    public function isPropertiesFromSource(): bool
    {
        return $this->propertiesFromSource;
    }

    public function setPropertiesFromSource(bool $propertiesFromSource): HardLink
    {
        $this->propertiesFromSource = $propertiesFromSource;

        return $this;
    }

    public function isChildrenFromSource(): bool
    {
        return $this->childrenFromSource;
    }

    public function setChildrenFromSource(bool $childrenFromSource): HardLink
    {
        $this->childrenFromSource = $childrenFromSource;

        return $this;
    }
}
