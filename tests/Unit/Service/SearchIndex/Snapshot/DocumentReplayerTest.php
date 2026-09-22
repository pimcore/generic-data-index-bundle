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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileReader;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileWriter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentReplayer;
use Pimcore\SearchClient\SearchClientInterface;

final class DocumentReplayerTest extends Unit
{
    /** @var string[] */
    private array $paths = [];

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

    public function testFlushesBeforeALineThatWouldCrossTheByteBudget(): void
    {
        // three documents of about 60 bytes each, a budget that holds one but not two of them
        $path = $this->writeDocuments([
            ['system_fields' => ['id' => 1], 'standard_fields' => ['pad' => str_repeat('a', 20)]],
            ['system_fields' => ['id' => 2], 'standard_fields' => ['pad' => str_repeat('b', 20)]],
            ['system_fields' => ['id' => 3], 'standard_fields' => ['pad' => str_repeat('c', 20)]],
        ]);
        $lineBytes = $this->lineBytes($path);

        $flushes = $this->replay($path, bulkSize: 1000, bulkBytes: $lineBytes[0] + 10);

        $this->assertSame([1, 1, 1], array_column($flushes, 'documents'), 'no bulk holds two documents');
        $this->assertSame($lineBytes, array_column($flushes, 'bytes'), 'every bulk stays within the budget');
    }

    public function testASingleDocumentLargerThanTheBudgetIsStillSentOnItsOwn(): void
    {
        $path = $this->writeDocuments([
            ['system_fields' => ['id' => 1], 'standard_fields' => ['pad' => str_repeat('a', 200)]],
            ['system_fields' => ['id' => 2], 'standard_fields' => ['pad' => 'b']],
        ]);
        $lineBytes = $this->lineBytes($path);

        $flushes = $this->replay($path, bulkSize: 1000, bulkBytes: 50);

        $this->assertSame([1, 1], array_column($flushes, 'documents'));
        $this->assertSame($lineBytes, array_column($flushes, 'bytes'));
    }

    public function testDocumentCountLimitStillApplies(): void
    {
        $path = $this->writeDocuments([
            ['system_fields' => ['id' => 1]],
            ['system_fields' => ['id' => 2]],
            ['system_fields' => ['id' => 3]],
        ]);

        $flushes = $this->replay($path, bulkSize: 2, bulkBytes: 1024 * 1024);

        $this->assertSame([2, 1], array_column($flushes, 'documents'));
    }

    public function testSendsTheRawLinesUnchangedWithAnIndexActionPerDocument(): void
    {
        $documents = [
            [
                'system_fields' => ['id' => 41, 'key' => 'käse/über'],
                'standard_fields' => ['ratio' => 1.0, 'n' => null],
            ],
            ['system_fields' => ['id' => 42], 'standard_fields' => []],
        ];
        $path = $this->writeDocuments($documents);
        $rawLines = [];
        foreach ((new DocumentFileReader())->readRawLines($path) as $line) {
            $rawLines[] = $line->json;
        }

        $this->replay($path, bulkSize: 1000, bulkBytes: 1024 * 1024);

        $this->assertCount(1, $this->bulkBodies);
        $lines = explode("\n", rtrim($this->bulkBodies[0], "\n"));
        $this->assertCount(4, $lines, 'action line + document line per document');
        $this->assertSame(['index' => ['_index' => 'pimcore_asset', '_id' => 41]], json_decode($lines[0], true));
        $this->assertSame($rawLines[0], $lines[1], 'the document line is the snapshot line, byte for byte');
        $this->assertSame(['index' => ['_index' => 'pimcore_asset', '_id' => 42]], json_decode($lines[2], true));
        $this->assertSame($rawLines[1], $lines[3]);
        $this->assertSame('false', $this->bulkParams[0]['refresh'], 'never refresh per bulk request');
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
            $this->assertStringContainsString('failed to parse', $e->getMessage());
        }
    }

    public function testDocumentWithoutIntegerIdIsRejected(): void
    {
        $path = $this->writeDocuments([['system_fields' => ['id' => 'not-an-int']]]);

        $this->expectException(SnapshotImportException::class);
        $this->expectExceptionMessage('Document without integer system_fields.id');

        $this->replay($path, bulkSize: 1000, bulkBytes: 1024 * 1024);
    }

    /** @var string[] NDJSON bodies of the bulk requests the replayer sent, in order */
    private array $bulkBodies = [];

    /** @var array[] full params of those requests */
    private array $bulkParams = [];

    private array $bulkResponse = ['errors' => false, 'items' => []];

    /**
     * @return array<int, array{documents: int, bytes: int}> one entry per bulk commit, in order
     */
    private function replay(string $path, int $bulkSize, int $bulkBytes): array
    {
        $this->bulkBodies = [];
        $this->bulkParams = [];
        $client = $this->makeEmpty(SearchClientInterface::class, [
            'bulk' => function (array $params): array {
                $this->bulkParams[] = $params;
                $this->bulkBodies[] = $params['body'];

                return $this->bulkResponse;
            },
        ]);
        $replayer = new DocumentReplayer($client, new DocumentFileReader(), $bulkSize, $bulkBytes);
        $log = new TestHandler();
        $replayer->setLogger(new Logger('test', [$log]));
        $target = new IndexTarget('asset', 'pimcore_asset', 'asset');
        $entry = new ManifestIndex(
            'asset', 'asset', null, 'pimcore_asset-odd', 3, 'asset.ndjson.gz', 1, str_repeat('0', 64),
        );

        $replayer->replay($target, $entry, $path);

        $flushes = [];
        foreach ($log->getRecords() as $record) {
            if ($record->message === 'Snapshot import bulk') {
                $flushes[] = ['documents' => $record->context['documents'], 'bytes' => $record->context['bytes']];
            }
        }

        return $flushes;
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

    /** @return int[] raw byte size of every line, in order */
    private function lineBytes(string $path): array
    {
        $bytes = [];
        foreach ((new DocumentFileReader())->readLines($path) as $line) {
            $bytes[] = $line->bytes;
        }

        return $bytes;
    }
}
