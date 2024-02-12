<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Traits;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\PaginatedSearchInterface;
use Symfony\Component\Validator\Constraints as Assert;

trait PaginatedSearchTrait
{
    #[Assert\Positive]
    private int $page = 1;
    #[Assert\Positive]
    private int $pageSize = PaginatedSearchInterface::DEFAULT_PAGE_SIZE;

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(int $page): self
    {
        $this->page = $page;
        return $this;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function setPageSize(int $pageSize): self
    {
        $this->pageSize = $pageSize;
        return $this;
    }
}