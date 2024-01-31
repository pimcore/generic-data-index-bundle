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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\Search\Pagination;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Paging\PaginationInfo;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Search\Pagination\PaginationInfoServiceInterface;

/**
 * @internal
 */
final class PaginationInfoService implements PaginationInfoServiceInterface
{
    public function getPaginationInfoFromSearchResult(array $searchResult, int $page, int $pageSize): PaginationInfo
    {
        return new PaginationInfo(
            totalItems: $searchResult['total']['value'],
            page: $page,
            pageSize: $pageSize,
            totalPages: (int)ceil($searchResult['total']['value'] / $pageSize)
        );
    }
}
