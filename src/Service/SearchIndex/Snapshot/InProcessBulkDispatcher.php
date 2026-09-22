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
 * Sends every chunk synchronously from the importing process: one bulk request in flight.
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
        $this->indexShortName = $target->shortName;
    }

    public function dispatch(BulkChunk $chunk): void
    {
        $body = file_get_contents($chunk->path);
        if ($body === false) {
            throw new SnapshotImportException(sprintf('Cannot read bulk chunk "%s"', $chunk->path));
        }
        $this->logger?->debug('Snapshot import bulk', [
            'index' => $this->indexShortName,
            'documents' => $chunk->documents,
            'bytes' => $chunk->bytes,
        ]);

        try {
            $this->bulkSender->send($body, $this->indexShortName);
        } finally {
            @unlink($chunk->path);
        }
    }

    public function finish(): void
    {
        // every chunk was sent synchronously in dispatch(); nothing is pending
    }

    public function abort(): void
    {
        // dispatch() deletes its chunk file even when sending fails; nothing is left to clean up
    }
}
