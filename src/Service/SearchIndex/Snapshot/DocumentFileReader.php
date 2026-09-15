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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;

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

    public function verifyHash(string $path, string $expectedSha256): void
    {
        $actual = hash_file('sha256', $path);
        if ($actual === false || !hash_equals($expectedSha256, $actual)) {
            throw new SnapshotImportException(sprintf(
                'Checksum mismatch for "%s": manifest says %s, file has %s',
                basename($path), $expectedSha256, (string) $actual
            ));
        }
    }

    /** @return iterable<array> */
    public function read(string $path): iterable
    {
        $handle = gzopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidSnapshotException(sprintf('Cannot open "%s" for gzip reading', $path));
        }

        try {
            $lineNumber = 0;
            while (($line = gzgets($handle)) !== false) {
                $lineNumber++;
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                try {
                    $document = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new InvalidSnapshotException(sprintf('Line %d of "%s" is not valid JSON: %s', $lineNumber, basename($path), $e->getMessage()));
                }
                if (!is_array($document)) {
                    throw new InvalidSnapshotException(sprintf('Line %d of "%s" is not a JSON object', $lineNumber, basename($path)));
                }
                yield $document;
            }
        } finally {
            gzclose($handle);
        }
    }
}
