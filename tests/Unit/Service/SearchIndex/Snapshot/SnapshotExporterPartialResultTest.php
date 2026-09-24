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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Snapshot;

use Codeception\Test\Unit;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotExportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultHit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ExportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Stats\IndexStats;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexStatsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotExporter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotIndexResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Psr\Log\NullLogger;

/**
 * A search response can be HTTP 200 and still be partial: `timed_out: true`, or failed shards
 * with only the surviving shards' hits. Recording those hits as the index's document count
 * would produce a snapshot that is incomplete and yet imports with matching counts.
 */
final class SnapshotExporterPartialResultTest extends Unit
{
    public function testATimedOutPageAbortsTheExport(): void
    {
        $this->expectException(SnapshotExportException::class);
        $this->expectExceptionMessage('partial');

        $this->export(['timed_out' => true, '_shards' => ['total' => 1, 'successful' => 1, 'failed' => 0]]);
    }

    public function testFailedShardsAbortTheExport(): void
    {
        $this->expectException(SnapshotExportException::class);
        $this->expectExceptionMessage('shard');

        $this->export(['timed_out' => false, '_shards' => ['total' => 2, 'successful' => 1, 'failed' => 1]]);
    }

    public function testACompleteResponseIsExported(): void
    {
        $storage = $this->export(['timed_out' => false, '_shards' => ['total' => 1, 'successful' => 1, 'failed' => 0]]);

        $this->assertTrue($storage->hasSnapshot('partial-check'));
        $this->assertSame(1, $storage->readManifest('partial-check')->getIndex('asset')->getDocumentCount());
    }

    /**
     * Exports one asset index whose single page comes back with the given response flags.
     */
    private function export(array $responseFlags): SnapshotStorage
    {
        $target = new IndexTarget('asset', 'pimcore_asset', 'asset');
        $hit = new SearchResultHit('1', 'pimcore_asset-odd', null, ['system_fields' => ['id' => 1]], [1]);
        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'existsAlias' => true,
            'getCurrentIndexVersion' => 'odd',
            'getCount' => 1,
            // one document on the first page, then the empty page that ends the paging loop
            'search' => static function (Search $search) use ($hit, $responseFlags): SearchResult {
                static $calls = 0;
                $hits = $calls++ === 0 ? [$hit] : [];

                $response = ['hits' => ['total' => ['value' => 1]]] + $responseFlags;

                return new SearchResult($hits, [], 1, null, $search, $response);
            },
        ]);
        $exporter = new SnapshotExporter(
            $searchIndexService,
            $this->makeEmpty(SearchIndexConfigServiceInterface::class, [
                'getClientType' => 'openSearch',
                'getIndexPrefix' => 'pimcore_',
            ]),
            $this->makeEmpty(SnapshotIndexResolverInterface::class, ['resolveAll' => [$target]]),
            $this->makeEmpty(SettingsStoreServiceInterface::class),
            $this->makeEmpty(IndexStatsServiceInterface::class, ['getStats' => new IndexStats(0, [])]),
            pageSize: 1000,
            pageBytes: 16 * 1024 * 1024,
        );
        $exporter->setLogger(new NullLogger());
        $storage = new SnapshotStorage(new Filesystem(new InMemoryFilesystemAdapter()));

        $exporter->export($storage, 'partial-check', new ExportOptions());

        return $storage;
    }
}
