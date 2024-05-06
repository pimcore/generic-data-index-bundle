<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
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
    ): PaginationInfo {
        return new PaginationInfo(
            totalItems: $searchResult->getTotalHits(),
            page: $page,
            pageSize: $pageSize,
            totalPages: $pageSize > 0 ? (int)ceil($searchResult->getTotalHits() / $pageSize) : 0
        );
    }
}
