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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileReader;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileWriter;

final class DocumentFileTest extends Unit
{
    /** @var string[] paths created by a test, always removed in _after() regardless of how the test exits */
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

    public function testRoundTripPreservesNestedLocalizedDocument(): void
    {
        $documents = [
            ['system_fields' => ['id' => 7, 'key' => 'käse/über'], 'standard_fields' => ['loc_name' => ['de' => 'Käse', 'en' => 'Cheese']]],
            ['system_fields' => ['id' => 8], 'standard_fields' => ['tags' => [], 'price' => 12.5, 'ratio' => 1.0, 'flag' => null]],
        ];
        $writer = DocumentFileWriter::createTemporary();
        foreach ($documents as $document) {
            $writer->write($document);
        }
        $written = $writer->finish();
        $this->paths[] = $written->path;

        $this->assertSame(2, $written->documentCount);
        $this->assertSame(filesize($written->path), $written->bytes);
        $this->assertSame(hash_file('sha256', $written->path), $written->sha256);
        $this->assertSame("\x1f\x8b", substr(file_get_contents($written->path), 0, 2), 'file is gzip');

        $reader = new DocumentFileReader();
        $reader->verifyHash($written->path, $written->sha256);
        $roundTripped = [];
        foreach ($reader->readRawLines($written->path) as $line) {
            $roundTripped[] = json_decode($line->json, true, 512, JSON_THROW_ON_ERROR);
        }
        $this->assertSame($documents, $roundTripped);
        $this->assertIsFloat($roundTripped[1]['standard_fields']['ratio'], 'whole-number float must not round-trip as int');
    }

    public function testHashMismatchIsRejectedBeforeReading(): void
    {
        $writer = DocumentFileWriter::createTemporary();
        $writer->write(['system_fields' => ['id' => 1]]);
        $written = $writer->finish();
        $this->paths[] = $written->path;

        $this->expectException(SnapshotImportException::class);
        (new DocumentFileReader())->verifyHash($written->path, str_repeat('0', 64));
    }

    public function testAbortRemovesTemporaryFile(): void
    {
        $writer = DocumentFileWriter::createTemporary();
        $writer->write(['system_fields' => ['id' => 1]]);
        $path = $writer->getPath();
        $this->paths[] = $path;
        $writer->abort();

        $this->assertFileDoesNotExist($path);
    }

    public function testAbortAfterFinishDoesNotDeleteOutput(): void
    {
        $writer = DocumentFileWriter::createTemporary();
        $writer->write(['system_fields' => ['id' => 1]]);
        $written = $writer->finish();
        $this->paths[] = $written->path;

        $writer->abort();

        $this->assertFileExists($written->path);
    }

    public function testWriterTracksDocumentCountAndRawBytesWhileWriting(): void
    {
        $writer = DocumentFileWriter::createTemporary();
        $this->paths[] = $writer->getPath();

        $this->assertSame(0, $writer->getDocumentCount());
        $this->assertSame(0, $writer->getRawBytes());

        $first = ['system_fields' => ['id' => 1, 'key' => 'käse']];
        $second = ['system_fields' => ['id' => 2], 'standard_fields' => ['ratio' => 1.0]];
        $writer->write($first);
        $writer->write($second);

        $expectedBytes = strlen(json_encode($first, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n")
            + strlen(json_encode($second, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) . "\n");
        $this->assertSame(2, $writer->getDocumentCount());
        $this->assertSame($expectedBytes, $writer->getRawBytes(), 'raw bytes are the uncompressed NDJSON size');

        $written = $writer->finish();
        $this->assertLessThan($expectedBytes + 64, $written->bytes, 'gzip output is unrelated to the raw byte counter');
    }

    public function testRawLinesAreTheExactFileLinesWithTheirByteSize(): void
    {
        $documents = [
            ['system_fields' => ['id' => 1, 'key' => 'käse']],
            ['system_fields' => ['id' => 2], 'standard_fields' => ['ratio' => 1.0]],
        ];
        $writer = DocumentFileWriter::createTemporary();
        foreach ($documents as $document) {
            $writer->write($document);
        }
        $written = $writer->finish();
        $this->paths[] = $written->path;
        $expected = explode("\n", rtrim((string) gzdecode((string) file_get_contents($written->path)), "\n"));

        $lines = iterator_to_array((new DocumentFileReader())->readRawLines($written->path), false);

        $this->assertSame($expected, array_map(static fn ($l) => $l->json, $lines), 'no decode/encode round trip');
        $this->assertSame(
            array_map(static fn (string $l) => strlen($l) + 1, $expected),
            array_map(static fn ($l) => $l->bytes, $lines),
            'bytes include the newline, like the writer counts them',
        );
        $this->assertSame($documents, array_map(static fn ($l) => json_decode($l->json, true), $lines));
    }
}
