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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Traits;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidModifierException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;

trait PaginatedSearchTrait
{
    private int $page = 1;

    private int $pageSize = SearchInterface::DEFAULT_PAGE_SIZE;

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(int $page): self
    {
        if ($page <= 0) {
            throw new InvalidModifierException('Page must be bigger then 0');
        }

        $this->page = $page;

        return $this;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function setPageSize(int $pageSize): self
    {
        if ($pageSize <= 0) {
            throw new InvalidModifierException('Page Size must be bigger then 0');
        }

        $this->pageSize = $pageSize;

        return $this;
    }
}
