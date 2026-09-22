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

use JsonException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\BulkChunk;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;

/**
 * Turns a snapshot file into bulk bodies on disk, one file per bulk request, bounded by the
 * document count and by the raw byte budget. The snapshot lines go into the body unchanged
 * (no decode/encode round trip); only the id is read from each line.
 *
 * @internal
 */
final class BulkChunkWriter
{
    /**
     * The exporter writes the source unchanged and GDI documents always start with the system
     * fields, id first; when a line deviates, the id is read the slow way (json_decode). At most
     * 18 digits, so the cast can never clamp an oversized value to PHP_INT_MAX: longer digit runs
     * take the slow path, where json_decode yields a float that the integer check rejects.
     */
    private const ID_FAST_PATH = '/^\{"system_fields":\{"id":(\d{1,18})[,}]/';

    public function __construct(
        private readonly DocumentFileReader $documentFileReader,
        private readonly int $bulkSize,
        private readonly int $bulkBytes,
    ) {
    }

    /**
     * @return iterable<BulkChunk> in file order; each chunk is complete on disk when yielded
     *
     * @throws SnapshotImportException
     */
    public function write(IndexTarget $target, ManifestIndex $entry, string $localFile): iterable
    {
        $body = '';
        $pending = 0;
        $pendingBytes = 0;
        foreach ($this->documentFileReader->readRawLines($localFile) as $line) {
            $id = $this->extractId($line->json, $entry);
            // Flush what is pending if this line would push the request over the byte budget,
            // so a bulk body never exceeds it by a document. A single document larger than the
            // whole budget still goes out on its own (post-add check below).
            if ($pending > 0 && $pendingBytes + $line->bytes > $this->bulkBytes) {
                yield $this->flush($body, $pending, $pendingBytes);
                $body = '';
                $pending = 0;
                $pendingBytes = 0;
            }
            $body .= '{"index":{"_index":"' . $target->aliasName . '","_id":' . $id . "}}\n" . $line->json . "\n";
            $pending++;
            $pendingBytes += $line->bytes;
            if ($pending >= $this->bulkSize || $pendingBytes >= $this->bulkBytes) {
                yield $this->flush($body, $pending, $pendingBytes);
                $body = '';
                $pending = 0;
                $pendingBytes = 0;
            }
        }
        if ($pending > 0) {
            yield $this->flush($body, $pending, $pendingBytes);
        }
    }

    /**
     * @throws SnapshotImportException
     */
    private function flush(string $body, int $documents, int $bytes): BulkChunk
    {
        $path = tempnam(DocumentFileWriter::temporaryDirectory(), 'gdi-snapshot-bulk-');
        if ($path === false || file_put_contents($path, $body) !== strlen($body)) {
            throw new SnapshotImportException('Cannot write a bulk chunk to the temporary directory');
        }

        return new BulkChunk($path, $documents, $bytes);
    }

    /**
     * @throws SnapshotImportException
     */
    private function extractId(string $json, ManifestIndex $entry): int
    {
        if (preg_match(self::ID_FAST_PATH, $json, $m) === 1) {
            return (int) $m[1];
        }

        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SnapshotImportException(
                sprintf('Invalid JSON document in "%s": %s', $entry->file, $e->getMessage()),
                0,
                $e,
            );
        }
        $id = is_array($document) ? ($document['system_fields']['id'] ?? null) : null;
        if (!is_int($id)) {
            throw new SnapshotImportException(
                sprintf('Document without integer system_fields.id in "%s"', $entry->file),
            );
        }

        return $id;
    }
}
