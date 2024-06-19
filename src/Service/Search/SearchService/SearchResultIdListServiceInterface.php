<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;

interface SearchResultIdListServiceInterface
{
    /**
     * Returns all IDs for all pages that match the search criteria ordered by ID.
     */
    public function getAllIds(SearchInterface $search): array;

    /**
     * Returns the IDs for the current page that match the search criteria ordered by defined sort order.
     */
    public function getIdsForCurrentPage(SearchInterface $search): array;
}