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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch;

use Exception;
use OpenSearch\Client;

/**
 * @internal
 */
interface OpenSearchServiceInterface
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

    public function getDocument(string $index, int $id): array;

    public function putMapping(array $params): array;

    public function countByAttributeValue(string $indexName, string $attribute, string $value): int;

    public function getOpenSearchClient(): Client;
}