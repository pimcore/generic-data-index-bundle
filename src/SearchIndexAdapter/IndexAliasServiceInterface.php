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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter;

/**
 * @internal
 */
interface IndexAliasServiceInterface
{
    public function addAlias(string $aliasName, string $indexName): array;

    public function existsAlias(string $aliasName, ?string $indexName = null): bool;

    public function deleteAlias(string $indexName, string $aliasName): array;

    public function getAllAliases(): array;

    public function updateAliases(string $alias, array $indexNames, array $existingIndexNames = []): ?array;
}
