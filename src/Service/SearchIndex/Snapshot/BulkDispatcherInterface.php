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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\BulkChunk;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;

/**
 * Sends the bulk chunks of one index to the search engine and deletes each chunk file once it
 * is sent. start() → dispatch()* → finish() on success; abort() after a failure.
 *
 * @internal
 */
interface BulkDispatcherInterface
{
    /**
     * @throws SnapshotImportException
     */
    public function start(IndexTarget $target): void;

    /**
     * Hands over one chunk; may block until a sender is free, and may report a failure of an
     * earlier chunk.
     *
     * @throws SnapshotImportException
     */
    public function dispatch(BulkChunk $chunk): void;

    /**
     * Waits until every dispatched chunk is sent.
     *
     * @throws SnapshotImportException
     */
    public function finish(): void;

    /**
     * Stops sending and removes chunk files that were not sent. Never throws.
     */
    public function abort(): void;
}
