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
use Psr\Log\LoggerInterface;

/**
 * Sends the chunks of one index from the importing process itself, one after the other.
 *
 * The fallback for a process that cannot talk to worker processes, see
 * {@see SubprocessPipeSupport}. One bulk request is in flight at a time, so the import is slower
 * than the worker pool but needs no subprocess and no pipe.
 *
 * @internal
 */
final class InProcessBulkDispatcher implements BulkDispatcherInterface
{
    private string $indexShortName = '';

    public function __construct(
        private readonly BulkSenderInterface $bulkSender,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function start(IndexTarget $target): void
    {
        $this->indexShortName = $target->getShortName();
    }

    /**
     * @throws SnapshotImportException
     */
    public function dispatch(BulkChunk $chunk): void
    {
        $this->logger?->debug('Snapshot import bulk', [
            'index' => $this->indexShortName,
            'worker' => 0,
            'documents' => $chunk->getDocuments(),
            'bytes' => $chunk->getBytes(),
        ]);

        try {
            $body = @file_get_contents($chunk->getPath());
            if ($body === false) {
                throw new SnapshotImportException(sprintf('Cannot read bulk chunk "%s"', $chunk->getPath()));
            }
            $this->bulkSender->send($body, $this->indexShortName);
        } finally {
            @unlink($chunk->getPath());
        }
    }

    /** Every chunk is sent by dispatch(), so there is nothing left in flight. */
    public function finish(): void
    {
    }

    /** dispatch() removes its own chunk file whether it succeeded or not. */
    public function abort(): void
    {
    }
}
