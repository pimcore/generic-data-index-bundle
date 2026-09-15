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
    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /** @var resource|null */
    private $handle;

    private int $documentCount = 0;

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

    public function write(array $document): void
    {
        if ($this->handle === null) {
            throw new SnapshotExportException('Writer is already closed');
        }
        gzwrite($this->handle, json_encode($document, self::JSON_FLAGS) . "\n");
        $this->documentCount++;
    }

    public function finish(): WrittenFile
    {
        if ($this->handle !== null) {
            gzclose($this->handle);
            $this->handle = null;
        }
        clearstatcache(true, $this->path);

        return new WrittenFile(
            path: $this->path,
            documentCount: $this->documentCount,
            bytes: (int) filesize($this->path),
            sha256: (string) hash_file('sha256', $this->path),
        );
    }

    public function abort(): void
    {
        if ($this->handle !== null) {
            gzclose($this->handle);
            $this->handle = null;
        }
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }
}
