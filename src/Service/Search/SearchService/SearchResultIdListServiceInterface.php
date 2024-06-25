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
