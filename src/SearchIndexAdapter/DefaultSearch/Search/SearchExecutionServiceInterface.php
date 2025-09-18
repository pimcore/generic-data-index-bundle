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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\SearchFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Debug\SearchInformation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;

/**
 * @internal
 */
interface SearchExecutionServiceInterface
{
    /**
     * Execute a search query.
     * Set $trackTotalHits = true to enable accurate hit counts, an integer to set a maximum count or leave it at null to use the engines default value.
     *
     * @throws SearchFailedException
     */
    public function executeSearch(
        AdapterSearchInterface $search,
        string $indexName,
        int|bool $trackTotalHits = null
    ): SearchResult;

    /**
     * @return SearchInformation[]
     */
    public function getExecutedSearches(): array;
}
