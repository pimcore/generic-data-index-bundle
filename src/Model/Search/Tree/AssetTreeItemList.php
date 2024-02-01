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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Tree;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Paging\PaginationInfo;

final class AssetTreeItemList
{
    public function __construct(
        /** @var AssetTreeItem[] */
        private readonly array $items,
        private readonly PaginationInfo $pagination,
    ) {
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
