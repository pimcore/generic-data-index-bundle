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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;

/**
 * @internal
 */
interface BulkSenderInterface
{
    /**
     * Sends one ready-made NDJSON bulk body (index action + document per line pair) to the
     * search engine, retrying with backoff while the engine rejects the request because its
     * write queue is full. The body is idempotent (index actions by _id), so resending it
     * whole is safe.
     *
     * @param string $indexShortName for error messages only
     *
     * @throws SnapshotImportException on item errors, non-retryable failures, or when the
     *                                 retries are exhausted
     */
    public function send(string $ndjsonBody, string $indexShortName): void;
}
