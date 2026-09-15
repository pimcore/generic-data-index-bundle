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

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\Manifest;
use Throwable;

/**
 * @internal
 */
final class SnapshotStorage implements SnapshotStorageInterface
{
    private const NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/';

    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly int $keep,
    ) {
    }

    public static function assertValidName(string $name): void
    {
        if (!preg_match(self::NAME_PATTERN, $name) || str_contains($name, '..')) {
            throw new InvalidSnapshotException(sprintf('Invalid snapshot name "%s"', $name));
        }
    }

    public function listSnapshots(): array
    {
        $byCreatedAt = [];
        foreach ($this->filesystem->listContents('', false) as $item) {
            /** @var StorageAttributes $item */
            if (!$item->isDir()) {
                continue;
            }
            $name = $item->path();
            if (!$this->hasSnapshot($name)) {
                continue;
            }

            try {
                $byCreatedAt[$name] = $this->readManifest($name)->createdAt;
            } catch (InvalidSnapshotException) {
                continue; // unreadable manifest: treat as incomplete
            }
        }
        uksort($byCreatedAt, static fn (string $a, string $b) => strcmp($byCreatedAt[$b], $byCreatedAt[$a]) ?: strcmp($b, $a));

        return array_keys($byCreatedAt);
    }

    public function latestSnapshotName(): ?string
    {
        return $this->listSnapshots()[0] ?? null;
    }

    public function hasSnapshot(string $name): bool
    {
        self::assertValidName($name);

        return $this->filesystem->fileExists($name . '/' . self::MANIFEST_FILE);
    }

    public function readManifest(string $name): Manifest
    {
        self::assertValidName($name);
        $path = $name . '/' . self::MANIFEST_FILE;
        if (!$this->filesystem->fileExists($path)) {
            throw new InvalidSnapshotException(sprintf('Snapshot "%s" has no manifest', $name));
        }

        try {
            $data = json_decode($this->filesystem->read($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new InvalidSnapshotException(sprintf('Snapshot "%s" manifest is not valid JSON: %s', $name, $e->getMessage()));
        }
        if (!is_array($data)) {
            throw new InvalidSnapshotException(sprintf('Snapshot "%s" manifest is not a JSON object', $name));
        }

        return Manifest::fromArray($data);
    }

    public function writeManifest(string $name, Manifest $manifest): void
    {
        self::assertValidName($name);
        $this->filesystem->write(
            $name . '/' . self::MANIFEST_FILE,
            json_encode($manifest->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function writeFile(string $name, string $file, string $localPath): void
    {
        self::assertValidName($name);
        $handle = fopen($localPath, 'rb');
        if ($handle === false) {
            throw new InvalidSnapshotException(sprintf('Cannot open "%s" for reading', $localPath));
        }

        try {
            $this->filesystem->writeStream($name . '/' . $file, $handle);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    public function readFileToLocal(string $name, string $file, string $localPath): void
    {
        self::assertValidName($name);
        $source = $this->filesystem->readStream($name . '/' . $file);
        $target = fopen($localPath, 'wb');
        if ($target === false) {
            throw new InvalidSnapshotException(sprintf('Cannot open "%s" for writing', $localPath));
        }

        try {
            stream_copy_to_stream($source, $target);
        } finally {
            fclose($target);
            if (is_resource($source)) {
                fclose($source);
            }
        }
    }

    public function deleteSnapshot(string $name): void
    {
        self::assertValidName($name);

        try {
            if ($this->filesystem->directoryExists($name)) {
                $this->filesystem->deleteDirectory($name);
            }
        } catch (FilesystemException) {
            // absent already
        }
    }

    public function rotate(): array
    {
        if ($this->keep <= 0) {
            return [];
        }
        $deleted = [];
        foreach (array_slice($this->listSnapshots(), $this->keep) as $name) {
            $this->deleteSnapshot($name);
            $deleted[] = $name;
        }

        return $deleted;
    }
}
