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

    public function addDeletion(
        string $indexName,
        int $id
    ): void;

    /**
     * @throws BulkOperationException
     */
    public function commit(?string $refreshIndex = null): void;
}
