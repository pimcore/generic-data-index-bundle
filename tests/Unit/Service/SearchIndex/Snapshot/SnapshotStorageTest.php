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
use League\Flysystem\DirectoryListing;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToReadFile;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\Manifest;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorage;

final class SnapshotStorageTest extends Unit
{
    private Filesystem $filesystem;

    /** @var string[] local temp files created by a test, removed in _after() */
    private array $tempFiles = [];

    protected function _before(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    }

    protected function _after(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->tempFiles = [];
    }

    public function testOnlyCompleteSnapshotsAreListedNewestFirst(): void
    {
        $storage = new SnapshotStorage($this->filesystem);
        $storage->writeManifest('older', $this->manifest('2026-09-01T00:00:00+00:00'));
        $storage->writeManifest('newer', $this->manifest('2026-09-10T00:00:00+00:00'));
        // aborted export: a data file but no manifest
        $this->filesystem->write('aborted/asset.ndjson.gz', 'partial');

        $this->assertSame(['newer', 'older'], $storage->listSnapshots());
        $this->assertSame('newer', $storage->latestSnapshotName());
        $this->assertFalse($storage->hasSnapshot('aborted'));
        $this->assertTrue($storage->hasSnapshot('older'));
    }

    public function testFileRoundTripAndDelete(): void
    {
        $storage = new SnapshotStorage($this->filesystem);
        $local = tempnam(sys_get_temp_dir(), 'gdi-test-');
        $this->tempFiles[] = $local;
        file_put_contents($local, 'payload');
        $storage->writeFile('snap', 'asset.ndjson.gz', $local);
        $back = tempnam(sys_get_temp_dir(), 'gdi-test-');
        $this->tempFiles[] = $back;
        $storage->readFileToLocal('snap', 'asset.ndjson.gz', $back);

        $this->assertSame('payload', file_get_contents($back));

        $storage->deleteSnapshot('snap');
        $this->assertFalse($this->filesystem->fileExists('snap/asset.ndjson.gz'));
        $storage->deleteSnapshot('snap'); // second delete is silent
    }

    public function testReadManifestOfMissingSnapshotThrows(): void
    {
        $storage = new SnapshotStorage($this->filesystem);

        $this->expectException(InvalidSnapshotException::class);
        $storage->readManifest('missing');
    }

    /** @dataProvider invalidNames */
    public function testRejectsUnsafeNames(string $name): void
    {
        $this->expectException(InvalidSnapshotException::class);
        SnapshotStorage::assertValidName($name);
    }

    public static function invalidNames(): array
    {
        return [['../etc'], ['a/b'], [''], ['.hidden'], [str_repeat('x', 129)], ["snapshot\n"]];
    }

    public function testEmptyStorageHasNoSnapshots(): void
    {
        $storage = new SnapshotStorage($this->filesystem);

        $this->assertSame([], $storage->listSnapshots());
        $this->assertNull($storage->latestSnapshotName());
    }

    public function testForeignDirectoriesWithInvalidNamesAreSkipped(): void
    {
        $storage = new SnapshotStorage($this->filesystem);
        $storage->writeManifest('valid', $this->manifest('2026-09-01T00:00:00+00:00'));
        $this->filesystem->write('.trash/x.txt', 'x');
        $this->filesystem->write('bad name/x.txt', 'x');

        $this->assertSame(['valid'], $storage->listSnapshots());
        $this->assertTrue($this->filesystem->fileExists('.trash/x.txt'));
        $this->assertTrue($this->filesystem->fileExists('bad name/x.txt'));
    }

    public function testManifestWithNonArrayIndexEntryIsTreatedAsIncomplete(): void
    {
        $storage = new SnapshotStorage($this->filesystem);
        $corrupted = $this->manifest('2026-09-01T00:00:00+00:00')->toArray();
        $corrupted['indices'] = [1, 2];
        $this->filesystem->write(
            'corrupt/' . SnapshotStorage::MANIFEST_FILE,
            json_encode($corrupted, JSON_THROW_ON_ERROR)
        );
        $storage->writeManifest('good', $this->manifest('2026-09-02T00:00:00+00:00'));

        $this->assertSame(['good'], $storage->listSnapshots());
        $this->assertSame('good', $storage->latestSnapshotName());

        $this->expectException(InvalidSnapshotException::class);
        $storage->readManifest('corrupt');
    }

    public function testDeleteSnapshotPropagatesRealDeleteFailure(): void
    {
        $storage = new SnapshotStorage($this->filesystem);
        $storage->writeManifest('snap', $this->manifest('2026-09-01T00:00:00+00:00'));
        $failing = new SnapshotStorage(new FailingDeleteFilesystemOperator($this->filesystem));

        $this->expectException(UnableToDeleteDirectory::class);
        $failing->deleteSnapshot('snap');
    }

    public function testReadManifestPropagatesRealReadFailureInsteadOfInvalidSnapshot(): void
    {
        $storage = new SnapshotStorage($this->filesystem);
        $storage->writeManifest('x', $this->manifest('2026-09-01T00:00:00+00:00'));
        $failing = new SnapshotStorage(new FailingReadFilesystemOperator($this->filesystem));

        $this->expectException(FilesystemException::class);
        $failing->readManifest('x');
    }

    public function testListSnapshotsPropagatesRealReadFailureInsteadOfSkippingIt(): void
    {
        $storage = new SnapshotStorage($this->filesystem);
        $storage->writeManifest('x', $this->manifest('2026-09-01T00:00:00+00:00'));
        $failing = new SnapshotStorage(new FailingReadFilesystemOperator($this->filesystem));

        $this->expectException(FilesystemException::class);
        $failing->listSnapshots();
    }

    public function testListSnapshotsStillSkipsAManifestWithInvalidJson(): void
    {
        // Existing behaviour, kept covered alongside the new read-failure propagation above:
        // a manifest that reads fine but fails to parse is still treated as incomplete.
        $storage = new SnapshotStorage($this->filesystem);
        $this->filesystem->write('bad-json/' . SnapshotStorage::MANIFEST_FILE, '{not json');
        $storage->writeManifest('good', $this->manifest('2026-09-02T00:00:00+00:00'));

        $this->assertSame(['good'], $storage->listSnapshots());
    }

    public function testNumericSnapshotNamesStayStrings(): void
    {
        // "123" is a valid NAME_PATTERN match; it must never be silently cast to an int by
        // being used as an array key internally (listSnapshots/latestSnapshotName rely on it
        // staying a string).
        $storage = new SnapshotStorage($this->filesystem);
        $storage->writeManifest('alpha', $this->manifest('2026-09-01T00:00:00+00:00'));
        $storage->writeManifest('123', $this->manifest('2026-09-02T00:00:00+00:00'));

        $this->assertTrue($storage->hasSnapshot('123'));
        $this->assertSame(['123', 'alpha'], $storage->listSnapshots());
        $this->assertSame('123', $storage->latestSnapshotName());
    }

    private function manifest(string $createdAt): Manifest
    {
        return new Manifest(
            createdAt: $createdAt,
            genericDataIndexVersion: 'dev',
            pimcoreVersion: 'dev',
            clientType: 'openSearch',
            indexPrefix: 'pimcore_',
            queueEntriesBefore: 0,
            queueEntriesAfter: 0,
            durationSeconds: 1,
            classMappingChecksums: [],
            indices: [],
        );
    }
}

/**
 * Test double: delegates every operation to a wrapped filesystem except
 * `deleteDirectory()`, which always fails, to prove real delete failures propagate.
 */
final class FailingDeleteFilesystemOperator implements FilesystemOperator
{
    public function __construct(private readonly FilesystemOperator $inner)
    {
    }

    public function fileExists(string $location): bool
    {
        return $this->inner->fileExists($location);
    }

    public function directoryExists(string $location): bool
    {
        return $this->inner->directoryExists($location);
    }

    public function has(string $location): bool
    {
        return $this->inner->has($location);
    }

    public function read(string $location): string
    {
        return $this->inner->read($location);
    }

    public function readStream(string $location)
    {
        return $this->inner->readStream($location);
    }

    public function listContents(string $location, bool $deep = self::LIST_SHALLOW): DirectoryListing
    {
        return $this->inner->listContents($location, $deep);
    }

    public function lastModified(string $path): int
    {
        return $this->inner->lastModified($path);
    }

    public function fileSize(string $path): int
    {
        return $this->inner->fileSize($path);
    }

    public function mimeType(string $path): string
    {
        return $this->inner->mimeType($path);
    }

    public function visibility(string $path): string
    {
        return $this->inner->visibility($path);
    }

    public function write(string $location, string $contents, array $config = []): void
    {
        $this->inner->write($location, $contents, $config);
    }

    public function writeStream(string $location, $contents, array $config = []): void
    {
        $this->inner->writeStream($location, $contents, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->inner->setVisibility($path, $visibility);
    }

    public function delete(string $location): void
    {
        $this->inner->delete($location);
    }

    public function deleteDirectory(string $location): void
    {
        throw UnableToDeleteDirectory::atLocation($location, 'simulated failure for testing');
    }

    public function createDirectory(string $location, array $config = []): void
    {
        $this->inner->createDirectory($location, $config);
    }

    public function move(string $source, string $destination, array $config = []): void
    {
        $this->inner->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, array $config = []): void
    {
        $this->inner->copy($source, $destination, $config);
    }
}

/**
 * Test double: delegates every operation to a wrapped filesystem except `read()`, which always
 * fails, to prove a real storage read failure propagates instead of being reclassified as an
 * invalid/incomplete manifest.
 */
final class FailingReadFilesystemOperator implements FilesystemOperator
{
    public function __construct(private readonly FilesystemOperator $inner)
    {
    }

    public function fileExists(string $location): bool
    {
        return $this->inner->fileExists($location);
    }

    public function directoryExists(string $location): bool
    {
        return $this->inner->directoryExists($location);
    }

    public function has(string $location): bool
    {
        return $this->inner->has($location);
    }

    public function read(string $location): string
    {
        throw UnableToReadFile::fromLocation($location, 'simulated failure for testing');
    }

    public function readStream(string $location)
    {
        return $this->inner->readStream($location);
    }

    public function listContents(string $location, bool $deep = self::LIST_SHALLOW): DirectoryListing
    {
        return $this->inner->listContents($location, $deep);
    }

    public function lastModified(string $path): int
    {
        return $this->inner->lastModified($path);
    }

    public function fileSize(string $path): int
    {
        return $this->inner->fileSize($path);
    }

    public function mimeType(string $path): string
    {
        return $this->inner->mimeType($path);
    }

    public function visibility(string $path): string
    {
        return $this->inner->visibility($path);
    }

    public function write(string $location, string $contents, array $config = []): void
    {
        $this->inner->write($location, $contents, $config);
    }

    public function writeStream(string $location, $contents, array $config = []): void
    {
        $this->inner->writeStream($location, $contents, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->inner->setVisibility($path, $visibility);
    }

    public function delete(string $location): void
    {
        $this->inner->delete($location);
    }

    public function deleteDirectory(string $location): void
    {
        $this->inner->deleteDirectory($location);
    }

    public function createDirectory(string $location, array $config = []): void
    {
        $this->inner->createDirectory($location, $config);
    }

    public function move(string $source, string $destination, array $config = []): void
    {
        $this->inner->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, array $config = []): void
    {
        $this->inner->copy($source, $destination, $config);
    }
}
