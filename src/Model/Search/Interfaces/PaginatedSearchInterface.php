<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces;

interface PaginatedSearchInterface extends SearchInterface
{
    public const DEFAULT_PAGE_SIZE = 50;
    public function getPage(): int;
    public function getPageSize(): int;
}