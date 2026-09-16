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
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToDeleteDirectory;
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
        $storage = new SnapshotStorage($this->filesystem, 0);
        $storage->writeManifest('older', $this->manifest('2026-09-01T00:00:00+00:00'));
        $storage->writeManifest('newer', $this->manifest('2026-09-10T00:00:00+00:00'));
        // aborted export: a data file but no manifest
        $this->filesystem->write('aborted/asset.ndjson.gz', 'partial');

        $this->assertSame(['newer', 'older'], $storage->listSnapshots());
        $this->assertSame('newer', $storage->latestSnapshotName());
        $this->assertFalse($storage->hasSnapshot('aborted'));
        $this->assertTrue($storage->hasSnapshot('older'));
    }

    public function testRotateKeepsNewestCompleteAndIgnoresIncomplete(): void
    {
        $storage = new SnapshotStorage($this->filesystem, 2);
        foreach (['a' => '2026-09-01', 'b' => '2026-09-02', 'c' => '2026-09-03'] as $name => $day) {
            $storage->writeManifest($name, $this->manifest($day . 'T00:00:00+00:00'));
            $this->filesystem->write($name . '/asset.ndjson.gz', 'x');
        }
        $this->filesystem->write('incomplete/asset.ndjson.gz', 'x');

        $this->assertSame(['a'], $storage->rotate());
        $this->assertSame(['c', 'b'], $storage->listSnapshots());
        $this->assertFalse($this->filesystem->fileExists('a/asset.ndjson.gz'));
        $this->assertTrue($this->filesystem->fileExists('incomplete/asset.ndjson.gz'));
    }

    public function testRotateWithZeroKeepsEverything(): void
    {
        $storage = new SnapshotStorage($this->filesystem, 0);
        $storage->writeManifest('a', $this->manifest('2026-09-01T00:00:00+00:00'));
        $storage->writeManifest('b', $this->manifest('2026-09-02T00:00:00+00:00'));

        $this->assertSame([], $storage->rotate());
        $this->assertCount(2, $storage->listSnapshots());
    }

    public function testFileRoundTripAndDelete(): void
    {
        $storage = new SnapshotStorage($this->filesystem, 0);
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
        $storage = new SnapshotStorage($this->filesystem, 0);

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
        $storage = new SnapshotStorage($this->filesystem, 0);

        $this->assertSame([], $storage->listSnapshots());
        $this->assertNull($storage->latestSnapshotName());
    }

    public function testForeignDirectoriesWithInvalidNamesAreSkipped(): void
    {
        $storage = new SnapshotStorage($this->filesystem, 1);
        $storage->writeManifest('valid', $this->manifest('2026-09-01T00:00:00+00:00'));
        $this->filesystem->write('.trash/x.txt', 'x');
        $this->filesystem->write('bad name/x.txt', 'x');

        $this->assertSame(['valid'], $storage->listSnapshots());
        $this->assertSame([], $storage->rotate());
        $this->assertTrue($this->filesystem->fileExists('.trash/x.txt'));
        $this->assertTrue($this->filesystem->fileExists('bad name/x.txt'));
    }

    public function testManifestWithNonArrayIndexEntryIsTreatedAsIncomplete(): void
    {
        $storage = new SnapshotStorage($this->filesystem, 0);
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
        $storage = new SnapshotStorage($this->filesystem, 0);
        $storage->writeManifest('snap', $this->manifest('2026-09-01T00:00:00+00:00'));
        $failing = new SnapshotStorage(new FailingDeleteFilesystemOperator($this->filesystem), 0);

        $this->expectException(UnableToDeleteDirectory::class);
        $failing->deleteSnapshot('snap');
    }

    public function testRotateDoesNotReportAFailedDeleteAsDeleted(): void
    {
        $storage = new SnapshotStorage($this->filesystem, 1);
        $storage->writeManifest('old', $this->manifest('2026-09-01T00:00:00+00:00'));
        $storage->writeManifest('new', $this->manifest('2026-09-02T00:00:00+00:00'));
        $failing = new SnapshotStorage(new FailingDeleteFilesystemOperator($this->filesystem), 1);

        try {
            $failing->rotate();
            $this->fail('Expected UnableToDeleteDirectory to propagate from rotate()');
        } catch (UnableToDeleteDirectory) {
            // expected: a real delete failure must propagate, not be swallowed and reported as deleted
        }

        $this->assertTrue($storage->hasSnapshot('old'), 'snapshot must still exist after a failed delete');
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
