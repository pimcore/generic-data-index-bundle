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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\BulkChunk;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\BulkChunkWriter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileReader;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileWriter;

final class BulkChunkWriterTest extends Unit
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
    }

    public function testWritesIndexActionAndRawLinePairsAndSplitsByCountAndBytes(): void
    {
        $documents = [
            ['system_fields' => ['id' => 41, 'key' => 'käse/über'], 'standard_fields' => ['ratio' => 1.0]],
            ['system_fields' => ['id' => 42]],
            ['system_fields' => ['id' => 43]],
        ];
        $file = $this->writeSnapshot($documents);
        $raw = $this->rawLines($file);

        $chunks = $this->chunks($file, bulkSize: 2, bulkBytes: 1024 * 1024);

        $this->assertSame([2, 1], array_map(static fn (BulkChunk $c) => $c->documents, $chunks), 'count limit');
        $this->assertSame(
            [strlen($raw[0]) + strlen($raw[1]) + 2, strlen($raw[2]) + 1],
            array_map(static fn (BulkChunk $c) => $c->bytes, $chunks),
            'bytes count the raw snapshot lines incl. newline, not the action lines',
        );
        $body = explode("\n", rtrim(file_get_contents($chunks[0]->path), "\n"));
        $this->assertSame(['index' => ['_index' => 'pimcore_asset', '_id' => 41]], json_decode($body[0], true));
        $this->assertSame($raw[0], $body[1], 'the document line is the snapshot line, byte for byte');
        $this->assertSame(['index' => ['_index' => 'pimcore_asset', '_id' => 42]], json_decode($body[2], true));
        $this->assertSame($raw[1], $body[3]);
    }

    public function testFlushesBeforeALineThatWouldCrossTheByteBudget(): void
    {
        $file = $this->writeSnapshot([
            ['system_fields' => ['id' => 1], 'standard_fields' => ['pad' => str_repeat('a', 20)]],
            ['system_fields' => ['id' => 2], 'standard_fields' => ['pad' => str_repeat('b', 20)]],
            ['system_fields' => ['id' => 3], 'standard_fields' => ['pad' => str_repeat('c', 20)]],
        ]);
        $lineBytes = array_map(static fn (string $l) => strlen($l) + 1, $this->rawLines($file));

        $chunks = $this->chunks($file, bulkSize: 1000, bulkBytes: $lineBytes[0] + 10);

        $this->assertSame([1, 1, 1], array_map(static fn (BulkChunk $c) => $c->documents, $chunks));
        $this->assertSame($lineBytes, array_map(static fn (BulkChunk $c) => $c->bytes, $chunks));
    }

    public function testASingleDocumentLargerThanTheBudgetStillGetsItsOwnChunk(): void
    {
        $file = $this->writeSnapshot([
            ['system_fields' => ['id' => 1], 'standard_fields' => ['pad' => str_repeat('a', 200)]],
            ['system_fields' => ['id' => 2]],
        ]);

        $chunks = $this->chunks($file, bulkSize: 1000, bulkBytes: 50);

        $this->assertSame([1, 1], array_map(static fn (BulkChunk $c) => $c->documents, $chunks));
    }

    public function testOversizedNumericIdIsRejectedInsteadOfClamped(): void
    {
        $file = $this->writeSnapshot([['system_fields' => ['id' => 1]]]);
        file_put_contents($file, gzencode('{"system_fields":{"id":9223372036854775808}}' . "\n"));

        $this->expectException(SnapshotImportException::class);
        $this->expectExceptionMessage('Document without integer system_fields.id');

        $this->chunks($file, bulkSize: 1000, bulkBytes: 1024 * 1024);
    }

    public function testDocumentWithoutIntegerIdIsRejected(): void
    {
        $file = $this->writeSnapshot([['system_fields' => ['id' => 'nope']]]);

        $this->expectException(SnapshotImportException::class);
        $this->expectExceptionMessage('Document without integer system_fields.id');

        $this->chunks($file, bulkSize: 1000, bulkBytes: 1024 * 1024);
    }

    /** @return BulkChunk[] */
    private function chunks(string $file, int $bulkSize, int $bulkBytes): array
    {
        $target = new IndexTarget('asset', 'pimcore_asset', 'asset');
        $entry = new ManifestIndex(
            'asset', 'asset', null, 'pimcore_asset-odd', 3, 'asset.ndjson.gz', 1, str_repeat('0', 64),
        );
        $writer = new BulkChunkWriter(new DocumentFileReader(), $bulkSize, $bulkBytes);
        $chunks = [];
        foreach ($writer->write($target, $entry, $file) as $chunk) {
            $this->paths[] = $chunk->path;
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    private function writeSnapshot(array $documents): string
    {
        $writer = DocumentFileWriter::createTemporary();
        foreach ($documents as $document) {
            $writer->write($document);
        }
        $written = $writer->finish();
        $this->paths[] = $written->path;

        return $written->path;
    }

    /** @return string[] */
    private function rawLines(string $file): array
    {
        $lines = [];
        foreach ((new DocumentFileReader())->readRawLines($file) as $line) {
            $lines[] = $line->json;
        }

        return $lines;
    }
}
