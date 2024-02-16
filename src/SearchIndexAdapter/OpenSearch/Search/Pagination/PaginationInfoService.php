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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Pagination;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Paging\PaginationInfo;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Pagination\PaginationInfoServiceInterface;

/**
 * @internal
 */
final class PaginationInfoService implements PaginationInfoServiceInterface
{
    public function getPaginationInfoFromSearchResult(
        SearchResult $searchResult,
        int $page,
        int $pageSize
    ): PaginationInfo
    {
        return new PaginationInfo(
            totalItems: $searchResult->getTotalHits(),
            page: $page,
            pageSize: $pageSize,
            totalPages: $pageSize > 0 ? (int)ceil($searchResult->getTotalHits() / $pageSize) : 0
        );
    }
}
