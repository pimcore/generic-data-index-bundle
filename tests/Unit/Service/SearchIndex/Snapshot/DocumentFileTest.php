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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileReader;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileWriter;

final class DocumentFileTest extends Unit
{
    public function testRoundTripPreservesNestedLocalizedDocument(): void
    {
        $documents = [
            ['system_fields' => ['id' => 7, 'key' => 'käse/über'], 'standard_fields' => ['loc_name' => ['de' => 'Käse', 'en' => 'Cheese']]],
            ['system_fields' => ['id' => 8], 'standard_fields' => ['tags' => [], 'price' => 12.5, 'flag' => null]],
        ];
        $writer = DocumentFileWriter::createTemporary();
        foreach ($documents as $document) {
            $writer->write($document);
        }
        $written = $writer->finish();

        $this->assertSame(2, $written->documentCount);
        $this->assertSame(filesize($written->path), $written->bytes);
        $this->assertSame(hash_file('sha256', $written->path), $written->sha256);
        $this->assertSame("\x1f\x8b", substr(file_get_contents($written->path), 0, 2), 'file is gzip');

        $reader = new DocumentFileReader();
        $reader->verifyHash($written->path, $written->sha256);
        $this->assertSame($documents, iterator_to_array($reader->read($written->path), false));
        unlink($written->path);
    }

    public function testHashMismatchIsRejectedBeforeReading(): void
    {
        $writer = DocumentFileWriter::createTemporary();
        $writer->write(['system_fields' => ['id' => 1]]);
        $written = $writer->finish();

        $this->expectException(SnapshotImportException::class);
        (new DocumentFileReader())->verifyHash($written->path, str_repeat('0', 64));
        unlink($written->path);
    }

    public function testMalformedLineThrows(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'gdi-test-');
        $handle = gzopen($path, 'wb');
        gzwrite($handle, "{\"system_fields\":{\"id\":1}}\nnot json\n");
        gzclose($handle);

        $this->expectException(InvalidSnapshotException::class);
        iterator_to_array((new DocumentFileReader())->read($path), false);
        unlink($path);
    }

    public function testAbortRemovesTemporaryFile(): void
    {
        $writer = DocumentFileWriter::createTemporary();
        $writer->write(['system_fields' => ['id' => 1]]);
        $path = $writer->getPath();
        $writer->abort();

        $this->assertFileDoesNotExist($path);
    }
}
