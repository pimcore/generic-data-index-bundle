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

use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotExportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\WrittenFile;

/**
 * @internal
 */
final class DocumentFileWriter
{
    private const JSON_FLAGS = JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRESERVE_ZERO_FRACTION;

    /** @var resource|null */
    private $handle;

    private int $documentCount = 0;

    private bool $finished = false;

    private bool $aborted = false;

    private function __construct(private readonly string $path)
    {
        $handle = gzopen($path, 'wb6');
        if ($handle === false) {
            throw new SnapshotExportException(sprintf('Cannot open "%s" for gzip writing', $path));
        }
        $this->handle = $handle;
    }

    public static function createTemporary(): self
    {
        $path = tempnam(self::temporaryDirectory(), 'gdi-snapshot-');
        if ($path === false) {
            throw new SnapshotExportException('Cannot create a temporary file for the snapshot');
        }

        return new self($path);
    }

    public static function temporaryDirectory(): string
    {
        return defined('PIMCORE_SYSTEM_TEMP_DIRECTORY') ? PIMCORE_SYSTEM_TEMP_DIRECTORY : sys_get_temp_dir();
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @throws SnapshotExportException if the writer was already closed via {@see finish()} or
     *                                  {@see abort()}, or if the document could not be fully written
     */
    public function write(array $document): void
    {
        if ($this->handle === null) {
            throw new SnapshotExportException('Writer is already closed');
        }
        $line = json_encode($document, self::JSON_FLAGS) . "\n";
        $written = gzwrite($this->handle, $line);
        if ($written === false || $written !== strlen($line)) {
            throw new SnapshotExportException(sprintf('Failed to write document to "%s"', $this->path));
        }
        $this->documentCount++;
    }

    /**
     * Closes the file and returns its final stats. May only be called once, and never after
     * {@see abort()}.
     *
     * @throws SnapshotExportException if the writer was already finished or aborted
     */
    public function finish(): WrittenFile
    {
        if ($this->aborted) {
            throw new SnapshotExportException('Writer was already aborted');
        }
        if ($this->finished) {
            throw new SnapshotExportException('Writer is already finished');
        }
        if ($this->handle !== null) {
            gzclose($this->handle);
            $this->handle = null;
        }
        $this->finished = true;
        clearstatcache(true, $this->path);

        return new WrittenFile(
            path: $this->path,
            documentCount: $this->documentCount,
            bytes: (int) filesize($this->path),
            sha256: (string) hash_file('sha256', $this->path),
        );
    }

    /**
     * Closes the handle (if still open) and unlinks the temporary file. A no-op with respect to
     * the file once {@see finish()} has already succeeded, so it never deletes a finished output
     * file.
     */
    public function abort(): void
    {
        if ($this->handle !== null) {
            gzclose($this->handle);
            $this->handle = null;
        }
        if ($this->finished) {
            $this->aborted = true;

            return;
        }
        if (file_exists($this->path)) {
            unlink($this->path);
        }
        $this->aborted = true;
    }
}
