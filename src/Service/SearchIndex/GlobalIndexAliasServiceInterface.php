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
