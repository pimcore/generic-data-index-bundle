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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;

/**
 * @internal
 */
interface SearchIndexServiceInterface
{
    public function refreshIndex(string $indexName): array;

    public function deleteIndex($indexName, bool $silent = false): void;

    public function getCurrentIndexVersion(string $indexName): string;

    /**
     * @throws Exception
     */
    public function reindex(string $indexName, array $mapping): void;

    public function createIndex(string $indexName, array $mappings = null): self;

    public function addAlias(string $aliasName, string $indexName): self;

    public function putAlias(string $aliasName, string $indexName): array;

    public function existsAlias(string $aliasName, string $indexName = null): bool;

    public function deleteAlias(string $indexName, string $aliasName): array;

    public function getDocument(string $index, int $id, bool $ignore404 = false): array;

    public function putMapping(array $params): array;

    public function countByAttributeValue(string $indexName, string $attribute, string $value): int;

    public function createPaginatedSearch(int $page, int $pageSize): AdapterSearchInterface;

    public function search(AdapterSearchInterface $search, string $indexName): SearchResult;

    public function getStats(string $indexName): array;
}
