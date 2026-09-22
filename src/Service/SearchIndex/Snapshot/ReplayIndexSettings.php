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

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexSettingsBackup;
use Pimcore\SearchClient\SearchClientInterface;

/**
 * While a snapshot file is replayed, the target index needs neither near-real-time visibility
 * nor a durable translog per request: the importer refreshes once after the last document, and
 * a crash mid-replay leaves an index that is recreated on the next attempt anyway. Disabling the
 * automatic refresh (one per second by default) and fsync-per-request removes measurable
 * overhead from a bulk load of millions of documents.
 *
 * Leaving bulk-loading mode flushes the index first: with an asynchronous translog a bulk
 * request is acknowledged before its operations are fsynced, and only the flush makes them
 * durable. Without it a crash right after the importer counted the documents and stamped the
 * class checksum could lose the tail of the replay while the class stays marked as current.
 *
 * @internal
 */
final class ReplayIndexSettings implements ReplayIndexSettingsInterface
{
    public function __construct(private readonly SearchClientInterface $client)
    {
    }

    public function apply(string $indexName): IndexSettingsBackup
    {
        $backup = $this->current($indexName);
        $this->put($indexName, ['refresh_interval' => '-1', 'translog' => ['durability' => 'async']]);

        return $backup;
    }

    public function restore(string $indexName, IndexSettingsBackup $backup): void
    {
        // A value the index had (the installation's configured index_settings) goes back as it
        // was; null resets the setting to the engine default instead of pinning a value.
        $settings = [
            'refresh_interval' => $backup->refreshInterval,
            'translog' => ['durability' => $backup->translogDurability],
        ];

        try {
            $this->flush($indexName);
        } catch (SnapshotImportException $flushError) {
            // The import of this index fails either way, but the index must not be left in
            // bulk-loading mode; report both errors if the reset fails as well.
            try {
                $this->put($indexName, $settings);
            } catch (SnapshotImportException $resetError) {
                throw new SnapshotImportException(
                    sprintf(
                        '%s Resetting the index settings also failed: %s',
                        $flushError->getMessage(),
                        $resetError->getMessage(),
                    ),
                    0,
                    $flushError,
                );
            }

            throw $flushError;
        }
        $this->put($indexName, $settings);
    }

    /**
     * The durability barrier: fsyncs the translog and commits the Lucene segments.
     *
     * @throws SnapshotImportException
     */
    private function flush(string $indexName): void
    {
        try {
            $response = $this->client->flushIndex(['index' => $indexName]);
        } catch (Exception $e) {
            throw new SnapshotImportException(
                sprintf('Cannot flush index "%s" after the replay: %s', $indexName, $e->getMessage()),
                0,
                $e,
            );
        }
        // a normal response can still report shards the flush did not reach: no barrier there
        $failedShards = (int) ($response['_shards']['failed'] ?? 0);
        if ($failedShards > 0) {
            throw new SnapshotImportException(sprintf(
                'Flush of index "%s" after the replay: %d shard(s) failed, the replayed documents are not durable',
                $indexName,
                $failedShards,
            ));
        }
    }

    /**
     * @throws SnapshotImportException
     */
    private function current(string $indexName): IndexSettingsBackup
    {
        try {
            $response = $this->client->getIndexSettings(['index' => $indexName]);
        } catch (Exception $e) {
            throw new SnapshotImportException(
                sprintf('Cannot read settings of index "%s": %s', $indexName, $e->getMessage()),
                0,
                $e,
            );
        }
        // keyed by the concrete index name, also when queried through its alias
        $index = is_array($response) && $response !== [] ? (reset($response)['settings']['index'] ?? []) : [];
        $refresh = $index['refresh_interval'] ?? null;
        $durability = $index['translog']['durability'] ?? null;

        return new IndexSettingsBackup(
            is_string($refresh) ? $refresh : null,
            is_string($durability) ? $durability : null,
        );
    }

    /**
     * @throws SnapshotImportException
     */
    private function put(string $indexName, array $indexSettings): void
    {
        try {
            $this->client->putIndexSettings(['index' => $indexName, 'body' => ['index' => $indexSettings]]);
        } catch (Exception $e) {
            throw new SnapshotImportException(
                sprintf('Cannot update settings of index "%s": %s', $indexName, $e->getMessage()),
                0,
                $e,
            );
        }
    }
}
