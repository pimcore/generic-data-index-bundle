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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\OpenSearch\SearchFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Debug\SearchInformation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;

/**
 * @internal
 */
interface SearchExecutionServiceInterface
{
    /**
     * @throws SearchFailedException
     */
    public function executeSearch(AdapterSearchInterface $search, string $indexName): SearchResult;

    /**
     * @return SearchInformation[]
     */
    public function getExecutedSearches(): array;
}
