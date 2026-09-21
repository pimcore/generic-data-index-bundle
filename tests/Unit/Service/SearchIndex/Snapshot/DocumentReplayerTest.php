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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\BulkOperationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileReader;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileWriter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentReplayer;

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

    /**
     * @return array<int, array{documents: int, bytes: int}> one entry per bulk commit, in order
     */
    private function replay(string $path, int $bulkSize, int $bulkBytes): array
    {
        $bulk = $this->makeEmpty(BulkOperationServiceInterface::class);
        $replayer = new DocumentReplayer($bulk, new DocumentFileReader(), $bulkSize, $bulkBytes);
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
