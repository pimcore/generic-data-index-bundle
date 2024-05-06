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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\BulkOperationException;

/**
 * @internal
 */
interface BulkOperationServiceInterface
{
    public function addUpdate(
        string $indexName,
        int $id,
        array $indexData,
        bool $upsert = true
    ): void;

    public function addDeletion(
        string $indexName,
        int $id
    ): void;

    /**
     * @throws BulkOperationException
     */
    public function commit(?string $refreshIndex = null): void;
}
