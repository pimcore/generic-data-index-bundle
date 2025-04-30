<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;

interface SearchResultIdListServiceInterface
{
    /**
     * Returns all IDs for all pages that match the search criteria ordered by defined sort order.
     */
    public function getAllIds(SearchInterface $search): array;

    /**
     * Returns the IDs for the current page that match the search criteria ordered by defined sort order.
     */
    public function getIdsForCurrentPage(SearchInterface $search): array;
}
