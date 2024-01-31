<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Paging;

final class PaginationInfo
{
    public function __construct(
        private readonly int $totalItems,
        private readonly int $page,
        private readonly int $pageSize,
        private readonly int $totalPages,
    )
    {
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }
}