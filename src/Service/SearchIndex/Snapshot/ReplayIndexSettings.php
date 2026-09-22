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
        $this->put($indexName, [
            'refresh_interval' => $backup->refreshInterval,
            'translog' => ['durability' => $backup->translogDurability],
        ]);
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
