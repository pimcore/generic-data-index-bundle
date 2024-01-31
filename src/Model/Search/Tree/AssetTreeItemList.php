<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Tree;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Paging\PaginationInfo;

class AssetTreeItemList
{
    public function __construct(
        /** @var AssetTreeItem[] */
        private readonly array          $items,
        private readonly PaginationInfo $pagination,
    )
    {
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getPagination(): PaginationInfo
    {
        return $this->pagination;
    }
}