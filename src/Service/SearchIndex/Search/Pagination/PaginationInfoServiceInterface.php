<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Search\Pagination;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Paging\PaginationInfo;

interface PaginationInfoServiceInterface
{
    public function getPaginationInfoFromSearchResult(array $searchResult, int $page, int $pageSize): PaginationInfo;
}