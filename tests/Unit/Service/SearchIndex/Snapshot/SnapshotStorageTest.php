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
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\Manifest;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorage;

final class SnapshotStorageTest extends Unit
{
    private Filesystem $filesystem;

    protected function _before(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
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
        file_put_contents($local, 'payload');
        $storage->writeFile('snap', 'asset.ndjson.gz', $local);
        $back = tempnam(sys_get_temp_dir(), 'gdi-test-');
        $storage->readFileToLocal('snap', 'asset.ndjson.gz', $back);

        $this->assertSame('payload', file_get_contents($back));

        $storage->deleteSnapshot('snap');
        $this->assertFalse($this->filesystem->fileExists('snap/asset.ndjson.gz'));
        $storage->deleteSnapshot('snap'); // second delete is silent
        unlink($local);
        unlink($back);
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
        return [['../etc'], ['a/b'], [''], ['.hidden'], [str_repeat('x', 129)]];
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
