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

    public function apply(string $indexName): void
    {
        $this->put($indexName, ['refresh_interval' => '-1', 'translog' => ['durability' => 'async']]);
    }

    public function restore(string $indexName): void
    {
        // null resets a setting to the engine default instead of pinning a value
        $this->put($indexName, ['refresh_interval' => null, 'translog' => ['durability' => null]]);
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
