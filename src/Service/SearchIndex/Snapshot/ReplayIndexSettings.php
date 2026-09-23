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
use Pimcore\SearchClient\SearchClientInterface;

/**
 * While a snapshot file is replayed, the target index needs no near-real-time visibility: the
 * importer refreshes once after the last document. Disabling the automatic refresh (one per
 * second by default) saves the refreshes and the segment churn they cause during a bulk load.
 * The translog keeps its default request durability, so every acknowledged bulk request is
 * durable and no flush barrier is needed before the replay counts as complete.
 *
 * @internal
 */
final class ReplayIndexSettings implements ReplayIndexSettingsInterface
{
    public function __construct(private readonly SearchClientInterface $client)
    {
    }

    public function disableRefresh(string $indexName): ?string
    {
        $previous = $this->currentRefreshInterval($indexName);
        $this->putRefreshInterval($indexName, '-1');

        return $previous;
    }

    public function restoreRefresh(string $indexName, ?string $previousRefreshInterval): void
    {
        // a configured value (the installation's index_settings) goes back as it was; null resets
        // the setting to the engine default instead of pinning a value
        $this->putRefreshInterval($indexName, $previousRefreshInterval);
    }

    /**
     * @throws SnapshotImportException
     */
    private function currentRefreshInterval(string $indexName): ?string
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

        return is_string($refresh) ? $refresh : null;
    }

    /**
     * @throws SnapshotImportException
     */
    private function putRefreshInterval(string $indexName, ?string $refreshInterval): void
    {
        try {
            $response = $this->client->putIndexSettings([
                'index' => $indexName,
                'body' => ['index' => ['refresh_interval' => $refreshInterval]],
            ]);
        } catch (Exception $e) {
            throw new SnapshotImportException(
                sprintf('Cannot update settings of index "%s": %s', $indexName, $e->getMessage()),
                0,
                $e,
            );
        }
        // the engine can answer without applying the change; the import must not go on then
        if (($response['acknowledged'] ?? false) !== true) {
            throw new SnapshotImportException(
                sprintf('Settings update of index "%s" was not acknowledged by the search engine', $indexName),
            );
        }
    }
}
