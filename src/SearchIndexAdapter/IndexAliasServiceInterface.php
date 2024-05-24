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
