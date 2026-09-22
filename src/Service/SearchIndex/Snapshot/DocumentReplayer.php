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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Throwable;

/**
 * Streams a snapshot file into the bulk API: the file is cut into ready-made bulk bodies on
 * disk (BulkChunkWriter) which a dispatcher sends — from this process, or through a pool of
 * worker processes so that several bulk requests are in flight at once (import_workers).
 *
 * For the duration of the replay the index runs in bulk-loading mode (no automatic refresh,
 * asynchronous translog); its previous settings are put back afterwards, also after a failure,
 * so a partial index never stays in that mode.
 *
 * @internal
 */
final class DocumentReplayer implements DocumentReplayerInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly BulkChunkWriter $chunkWriter,
        private readonly BulkDispatcherFactory $dispatcherFactory,
        private readonly ReplayIndexSettingsInterface $replayIndexSettings,
    ) {
    }

    public function replay(IndexTarget $target, ManifestIndex $entry, string $localFile): void
    {
        $backup = $this->replayIndexSettings->apply($target->aliasName);

        try {
            $this->stream($target, $entry, $localFile);
        } catch (Throwable $e) {
            try {
                $this->replayIndexSettings->restore($target->aliasName, $backup);
            } catch (SnapshotImportException $restoreError) {
                $this->logger?->warning('Could not restore index settings after a failed replay', [
                    'index' => $target->aliasName,
                    'error' => $restoreError->getMessage(),
                ]);
            }

            throw $e;
        }
        $this->replayIndexSettings->restore($target->aliasName, $backup);
    }

    /**
     * @throws SnapshotImportException
     */
    private function stream(IndexTarget $target, ManifestIndex $entry, string $localFile): void
    {
        $dispatcher = $this->dispatcherFactory->create();
        $dispatcher->start($target);

        try {
            foreach ($this->chunkWriter->write($target, $entry, $localFile) as $chunk) {
                $dispatcher->dispatch($chunk);
            }
            $dispatcher->finish();
        } catch (InvalidSnapshotException $e) {
            $dispatcher->abort();

            throw new SnapshotImportException(
                sprintf('Import of index "%s" failed: %s', $target->shortName, $e->getMessage()),
                0,
                $e,
            );
        } catch (Throwable $e) {
            $dispatcher->abort();

            throw $e;
        }
    }
}
