<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter;

/**
 * @internal
 */
interface IndexAliasServiceInterface
{
    public function addAlias(string $aliasName, string $indexName): array;

    public function existsAlias(string $aliasName, string $indexName = null): bool;

    public function deleteAlias(string $indexName, string $aliasName): array;

    public function getAllAliases(): array;

    public function updateAliases(string $alias, array $indexNames, array $existingIndexNames = []): ?array;
}