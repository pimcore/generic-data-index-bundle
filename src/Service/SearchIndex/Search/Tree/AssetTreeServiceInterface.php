<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Search\Tree;


use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Tree\AssetTreeItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Tree\AssetTreeItemList;

interface AssetTreeServiceInterface
{
    /**
     * @return AssetTreeItem[]
     */
    public function fetchTreeItems(int $parentId = 1, int $page = 1, int $pageSize = 50): AssetTreeItemList;
}