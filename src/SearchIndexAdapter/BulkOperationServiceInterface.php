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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\BulkOperationException;

/**
 * @internal
 */
interface BulkOperationServiceInterface
{
    public function add(
        string $indexName,
        int $id,
        array $indexData,
        bool $upsert = true
    ): void;

    public function addUpdate(
        string $indexName,
        int $id,
        array $updateData
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
