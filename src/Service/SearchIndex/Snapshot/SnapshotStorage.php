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
    private const NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/D';

    public function __construct(
        private readonly FilesystemOperator $filesystem,
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
        // Rows are collected as a list, never as array keys: a numeric-only snapshot name
        // (e.g. "123") is a valid NAME_PATTERN match, but PHP silently casts a numeric string
        // array key to int, which would make this method return ints instead of strings.
        $rows = [];
        foreach ($this->filesystem->listContents('', false) as $item) {
            /** @var StorageAttributes $item */
            if (!$item->isDir()) {
                continue;
            }
            $name = (string) $item->path();

            try {
                if (!$this->hasSnapshot($name)) {
                    continue;
                }
                $rows[] = ['name' => $name, 'createdAt' => $this->readManifest($name)->getCreatedAt()];
            } catch (InvalidSnapshotException) {
                continue; // invalid name or unreadable manifest: treat as incomplete
            }
        }
        usort(
            $rows,
            static fn (array $a, array $b) => strcmp($b['createdAt'], $a['createdAt'])
                ?: strcmp($b['name'], $a['name']),
        );

        return array_map(static fn (array $row) => $row['name'], $rows);
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

        // A read failure (e.g. League\Flysystem\FilesystemException from a transient storage
        // outage) must propagate as-is, not be reclassified as an invalid manifest: only actual
        // parsing failures below (bad JSON, or a manifest that fails Manifest::fromArray()) are
        // a reason to treat the snapshot as malformed/incomplete.
        $contents = $this->filesystem->read($path);

        return $this->parseManifest($name, $contents);
    }

    private function parseManifest(string $name, string $contents): Manifest
    {
        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new InvalidSnapshotException(
                sprintf('Snapshot "%s" manifest is not valid JSON: %s', $name, $e->getMessage()),
            );
        }
        if (!is_array($data)) {
            throw new InvalidSnapshotException(sprintf('Snapshot "%s" manifest is not a JSON object', $name));
        }

        try {
            return Manifest::fromArray($data);
        } catch (InvalidSnapshotException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidSnapshotException(
                sprintf('Snapshot "%s" manifest is invalid: %s', $name, $e->getMessage()),
            );
        }
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
        $target = fopen($localPath, 'wb');
        if ($target === false) {
            throw new InvalidSnapshotException(sprintf('Cannot open "%s" for writing', $localPath));
        }

        try {
            $source = $this->filesystem->readStream($name . '/' . $file);

            try {
                stream_copy_to_stream($source, $target);
            } finally {
                if (is_resource($source)) {
                    fclose($source);
                }
            }
        } finally {
            fclose($target);
        }
    }

    public function deleteSnapshot(string $name): void
    {
        self::assertValidName($name);

        if ($this->filesystem->directoryExists($name)) {
            $this->filesystem->deleteDirectory($name);
        }
    }
}
