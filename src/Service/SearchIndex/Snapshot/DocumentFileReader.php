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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\RawDocumentLine;

/**
 * @internal
 */
final class DocumentFileReader
{
    public function temporaryPath(): string
    {
        $path = tempnam(DocumentFileWriter::temporaryDirectory(), 'gdi-snapshot-in-');
        if ($path === false) {
            throw new SnapshotImportException('Cannot create a temporary file for the snapshot');
        }

        return $path;
    }

    /**
     * @throws SnapshotImportException
     */
    public function verifySize(string $path, int $expectedBytes): void
    {
        clearstatcache(true, $path);
        $actual = filesize($path);
        if ($actual === false || $actual !== $expectedBytes) {
            throw new SnapshotImportException(sprintf(
                'Size mismatch for "%s": manifest says %d bytes, file has %s',
                basename($path),
                $expectedBytes,
                $actual === false ? 'unknown size' : $actual . ' bytes',
            ));
        }
    }

    public function verifyHash(string $path, string $expectedSha256): void
    {
        $actual = hash_file('sha256', $path);
        if ($actual === false || !hash_equals($expectedSha256, $actual)) {
            throw new SnapshotImportException(sprintf(
                'Checksum mismatch for "%s": manifest says %s, file has %s',
                basename($path),
                $expectedSha256,
                (string) $actual,
            ));
        }
    }

    /** @return iterable<array> */
    /**
     * The lines as stored, undecoded, for callers that pass them on verbatim (the bulk replay).
     * Empty lines are skipped; anything else is left to the consumer to validate.
     *
     * @return iterable<RawDocumentLine>
     *
     * @throws InvalidSnapshotException
     */
    public function readRawLines(string $path): iterable
    {
        $handle = gzopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidSnapshotException(sprintf('Cannot open "%s" for gzip reading', $path));
        }

        try {
            while (($line = gzgets($handle)) !== false) {
                $bytes = strlen($line);
                $json = rtrim($line, "\r\n");
                if (trim($json) === '') {
                    continue;
                }
                yield new RawDocumentLine($json, $bytes);
            }
        } finally {
            gzclose($handle);
        }
    }
}
