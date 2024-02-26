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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Traits;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidModifierException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\PaginatedSearchInterface;

trait PaginatedSearchTrait
{
    private int $page = 1;

    private int $pageSize = PaginatedSearchInterface::DEFAULT_PAGE_SIZE;

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(int $page): void
    {
        if ($page <= 0) {
            throw new InvalidModifierException('Page must be bigger then 0');
        }

        $this->page = $page;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function setPageSize(int $pageSize): void
    {
        if ($pageSize <= 0) {
            throw new InvalidModifierException('Page Size must be bigger then 0');
        }

        $this->pageSize = $pageSize;
    }
}
