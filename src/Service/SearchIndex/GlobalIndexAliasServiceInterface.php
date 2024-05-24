<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

/**
 * @internal
 */
interface GlobalIndexAliasServiceInterface
{
    /**
     * Adds or updates the alias for the data object index as a combination of all indices for all data object types.
     */
    public function updateDataObjectAlias(): void;

    /**
     * Adds or updates the alias for the global element search index as a combination of all indices for all element types.
     */
    public function updateElementSearchAlias(): void;

    public function addToDataObjectAlias(string $indexName): void;

    public function addToElementSearchAlias(string $indexName): void;

    public function getDataObjectAliasName(): string;

    public function getElementSearchAliasName(): string;
}