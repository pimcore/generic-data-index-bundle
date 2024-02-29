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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchResult;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Paging\PaginationInfo;

final class DataObjectSearchResult
{
    public function __construct(
        /** @var DataObjectSearchResultItem[] */
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
