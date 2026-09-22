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
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexSettingsBackup;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\BulkChunkWriter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\BulkDispatcherFactory;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\BulkSender;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileReader;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileWriter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentReplayer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\ReplayIndexSettingsInterface;
use Pimcore\SearchClient\SearchClientInterface;

final class DocumentReplayerTest extends Unit
{
    /** @var string[] */
    private array $paths = [];

    /** @var string[] NDJSON bodies of the bulk requests, in order */
    private array $bulkBodies = [];

    /** @var string[] apply/restore calls on the settings service, in order */
    private array $settingsCalls = [];

    private array $bulkResponse = ['errors' => false, 'items' => []];

    protected function _after(): void
    {
        foreach ($this->paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->paths = [];
        $this->bulkResponse = ['errors' => false, 'items' => []];
    }

    public function testSendsEveryChunkAndLeavesNoChunkFilesBehind(): void
    {
        $path = $this->writeDocuments([
            ['system_fields' => ['id' => 1], 'standard_fields' => ['pad' => str_repeat('a', 20)]],
            ['system_fields' => ['id' => 2], 'standard_fields' => ['pad' => str_repeat('b', 20)]],
            ['system_fields' => ['id' => 3], 'standard_fields' => ['pad' => str_repeat('c', 20)]],
        ]);

        $this->replay($path, bulkSize: 2, bulkBytes: 1024 * 1024);

        $this->assertCount(2, $this->bulkBodies, 'two documents per bulk, then the rest');
        $this->assertStringContainsString('"_id":1', $this->bulkBodies[0]);
        $this->assertStringContainsString('"_id":3', $this->bulkBodies[1]);
        $leftovers = glob(sys_get_temp_dir() . '/gdi-snapshot-bulk-*') ?: [];
        $this->assertSame([], $leftovers, 'chunk files are deleted after sending');
    }

    public function testBulkItemErrorsAbortTheReplay(): void
    {
        $path = $this->writeDocuments([['system_fields' => ['id' => 1]], ['system_fields' => ['id' => 2]]]);
        $this->bulkResponse = ['errors' => true, 'items' => [
            ['index' => ['_id' => '1', 'status' => 201]],
            ['index' => [
                '_id' => '2',
                'status' => 400,
                'error' => ['type' => 'mapper_parsing_exception', 'reason' => 'failed to parse'],
            ]],
        ]];

        try {
            $this->replay($path, bulkSize: 1000, bulkBytes: 1024 * 1024);
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString('asset', $e->getMessage());
            $this->assertStringContainsString('mapper_parsing_exception', $e->getMessage());
        }
    }

    public function testBulkLoadingSettingsWrapTheReplayAndAreRestoredWithTheBackup(): void
    {
        $path = $this->writeDocuments([['system_fields' => ['id' => 1]]]);

        $this->replay($path, bulkSize: 1000, bulkBytes: 1024 * 1024);

        $this->assertSame(['apply pimcore_asset', 'restore pimcore_asset 30s'], $this->settingsCalls);
    }

    public function testSettingsAreRestoredWhenTheReplayFails(): void
    {
        $path = $this->writeDocuments([['system_fields' => ['id' => 1]]]);
        $this->bulkResponse = ['errors' => true, 'items' => [['index' => ['_id' => '1', 'error' => 'x']]]];

        try {
            $this->replay($path, bulkSize: 1000, bulkBytes: 1024 * 1024);
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException) {
            $this->assertSame(['apply pimcore_asset', 'restore pimcore_asset 30s'], $this->settingsCalls);
        }
    }

    public function testInvalidDocumentsAbortTheReplayBeforeAnythingIsSent(): void
    {
        $path = $this->writeDocuments([['system_fields' => ['id' => 'nope']]]);

        try {
            $this->replay($path, bulkSize: 1000, bulkBytes: 1024 * 1024);
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString('Document without integer system_fields.id', $e->getMessage());
            $this->assertSame([], $this->bulkBodies);
        }
    }

    private function replay(string $path, int $bulkSize, int $bulkBytes): void
    {
        $this->bulkBodies = [];
        $this->settingsCalls = [];
        $client = $this->makeEmpty(SearchClientInterface::class, [
            'bulk' => function (array $params): array {
                $this->bulkBodies[] = $params['body'];

                return $this->bulkResponse;
            },
        ]);
        $settings = $this->makeEmpty(ReplayIndexSettingsInterface::class, [
            'apply' => function (string $index): IndexSettingsBackup {
                $this->settingsCalls[] = 'apply ' . $index;

                return new IndexSettingsBackup('30s', null);
            },
            'restore' => function (string $index, IndexSettingsBackup $backup): void {
                $this->settingsCalls[] = 'restore ' . $index . ' ' . $backup->refreshInterval;
            },
        ]);
        $replayer = new DocumentReplayer(
            new BulkChunkWriter(new DocumentFileReader(), $bulkSize, $bulkBytes),
            new BulkDispatcherFactory(new BulkSender($client, static fn (int $ms) => null), 1, '/app', 'test', false),
            $settings,
        );
        $replayer->setLogger(new Logger('test', [new TestHandler()]));
        $target = new IndexTarget('asset', 'pimcore_asset', 'asset');
        $entry = new ManifestIndex(
            'asset', 'asset', null, 'pimcore_asset-odd', 3, 'asset.ndjson.gz', 1, str_repeat('0', 64),
        );

        $replayer->replay($target, $entry, $path);
    }

    private function writeDocuments(array $documents): string
    {
        $writer = DocumentFileWriter::createTemporary();
        foreach ($documents as $document) {
            $writer->write($document);
        }
        $written = $writer->finish();
        $this->paths[] = $written->path;

        return $written->path;
    }
}
