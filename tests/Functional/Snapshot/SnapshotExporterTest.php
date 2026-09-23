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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Snapshot;

use Codeception\Test\Unit;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotExportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ExportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\Manifest;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexStatsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileReader;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileWriter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotExporter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotExporterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotIndexResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorageInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Tests\Support\Util\TestHelper;
use RuntimeException;
use Throwable;

final class SnapshotExporterTest extends Unit
{
    protected IndexTester $tester;

    protected function _before(): void
    {
        $this->tester->enableSynchronousProcessing();
        $this->tester->clearQueue();
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    public function testExportsEveryDocumentAndWritesManifestLast(): void
    {
        $objects = [];
        for ($i = 1; $i <= 3; $i++) {
            $objects[] = $this->tester->createFullyFledgedObjectSimple('snapshot-export-', true, true, $i);
        }
        $this->tester->flushIndex();
        $storage = new SnapshotStorage(new Filesystem(new InMemoryFilesystemAdapter()));
        /** @var SnapshotExporterInterface $exporter */
        $exporter = $this->tester->grabService(SnapshotExporterInterface::class);

        $seen = [];
        $result = $exporter->export($storage, 'first', new ExportOptions(), static function (IndexTarget $target, int $count) use (&$seen): void {
            $seen[$target->shortName] = $count;
        });

        $manifest = $storage->readManifest('first');
        $this->assertSame($result->manifest->toArray(), $manifest->toArray());
        $simple = $manifest->getIndex('data-object_simple');
        $this->assertNotNull($simple);
        $this->assertSame(3, $simple->documentCount, 'every indexed Simple object is exported');
        $this->assertSame(3, $seen['data-object_simple']);
        $classId = ClassDefinition::getByName('simple')->getId();
        $this->assertSame($simple->classId, $classId);
        $this->assertArrayHasKey($classId, $manifest->classMappingChecksums);
        $this->assertSame(0, $manifest->queueEntriesBefore);
        $this->assertSame(1, $manifest->formatVersion);

        // the file really holds the documents, with the localized value intact
        $local = tempnam(sys_get_temp_dir(), 'gdi-test-');
        $storage->readFileToLocal('first', $simple->file, $local);
        (new DocumentFileReader())->verifyHash($local, $simple->sha256);
        $documents = [];
        foreach ((new DocumentFileReader())->readRawLines($local) as $line) {
            $documents[] = json_decode($line->json, true, 512, JSON_THROW_ON_ERROR);
        }
        $this->assertCount(3, $documents);
        $ids = array_map(static fn (array $d) => $d['system_fields']['id'], $documents);
        $this->assertEqualsCanonicalizing(array_map(static fn ($o) => $o->getId(), $objects), $ids);
        $this->assertSame($objects[0]->getLoc_name('de'), $documents[array_search($objects[0]->getId(), $ids, true)]['standard_fields']['loc_name']['de']);
        unlink($local);
    }

    public function testDryRunWritesNothing(): void
    {
        $storage = new SnapshotStorage(new Filesystem(new InMemoryFilesystemAdapter()));
        /** @var SnapshotExporterInterface $exporter */
        $exporter = $this->tester->grabService(SnapshotExporterInterface::class);

        $result = $exporter->export($storage, 'dry', new ExportOptions(dryRun: true));

        $this->assertTrue($result->dryRun);
        $this->assertSame([], $storage->listSnapshots());
    }

    public function testQueueCountsAreRecordedInTheManifest(): void
    {
        Db::get()->executeStatement(
            'INSERT INTO generic_data_index_queue (elementId, elementType, elementIndexName, operation, operationTime, dispatched) VALUES (?, \'dataObject\', \'simple\', \'update\', 1, 0)',
            [999999]
        );
        $storage = new SnapshotStorage(new Filesystem(new InMemoryFilesystemAdapter()));
        /** @var SnapshotExporterInterface $exporter */
        $exporter = $this->tester->grabService(SnapshotExporterInterface::class);

        try {
            $result = $exporter->export($storage, 'queue-counts', new ExportOptions());

            $this->assertSame(1, $result->manifest->queueEntriesBefore);
            $this->assertSame(1, $result->manifest->queueEntriesAfter);
            $this->assertTrue($storage->hasSnapshot('queue-counts'));
        } finally {
            $this->tester->clearQueue();
        }
    }

    public function testManifestWriteFailureRemovesPartialSnapshot(): void
    {
        $this->tester->createFullyFledgedObjectSimple('snapshot-manifest-fail-', true, true, 7);
        $this->tester->flushIndex();

        $tempDir = DocumentFileWriter::temporaryDirectory();
        $before = glob($tempDir . '/gdi-snapshot-*') ?: [];

        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $inner = new SnapshotStorage($filesystem);
        $storage = new DelegatingFailingSnapshotStorage($inner, ['writeManifest' => new RuntimeException('disk full')]);
        /** @var SnapshotExporterInterface $exporter */
        $exporter = $this->tester->grabService(SnapshotExporterInterface::class);

        try {
            $exporter->export($storage, 'manifest-fail', new ExportOptions());
            $this->fail('expected SnapshotExportException');
        } catch (SnapshotExportException $e) {
            $this->assertStringContainsString('disk full', $e->getMessage());
        }

        $this->assertSame([], $inner->listSnapshots());
        $this->assertFalse($filesystem->directoryExists('manifest-fail'));

        $after = glob($tempDir . '/gdi-snapshot-*') ?: [];
        $this->assertSame([], array_diff($after, $before), 'no leftover snapshot temp files after a manifest-write failure');
    }

    public function testCleanupFailureDoesNotMaskTheOriginalExportFailure(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $inner = new SnapshotStorage($filesystem);
        $originalFailure = new RuntimeException('disk full');
        // the first deleteSnapshot() call is the pre-export clearing of leftovers and must
        // succeed; the second one is the cleanup after the manifest failure, which is under test
        $storage = new DelegatingFailingSnapshotStorage($inner, [
            'writeManifest' => $originalFailure,
            'deleteSnapshot' => new RuntimeException('delete boom'),
        ], ['deleteSnapshot' => 2]);
        /** @var SnapshotExporterInterface $exporter */
        $exporter = $this->tester->grabService(SnapshotExporterInterface::class);

        try {
            $exporter->export($storage, 'cleanup-fail', new ExportOptions());
            $this->fail('expected SnapshotExportException');
        } catch (SnapshotExportException $e) {
            $this->assertStringContainsString('disk full', $e->getMessage());
            $this->assertStringContainsString('Cleanup of the partial snapshot also failed', $e->getMessage());
            $this->assertStringContainsString('delete boom', $e->getMessage());
            $this->assertSame($originalFailure, $e->getPrevious());
        }
    }

    public function testPagesShrinkToTheByteBudgetAndStillExportEveryDocument(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->tester->createFullyFledgedObjectSimple('snapshot-paged-', true, true, $i);
        }
        $this->tester->flushIndex();
        $storage = new SnapshotStorage(new Filesystem(new InMemoryFilesystemAdapter()));
        // ceiling of 2 documents, budget of 1 byte: a 1-document probe, then 1 document per page
        $exporter = new SnapshotExporter(
            $this->tester->grabService(SearchIndexServiceInterface::class),
            $this->tester->grabService(SearchIndexConfigServiceInterface::class),
            $this->tester->grabService(SnapshotIndexResolverInterface::class),
            $this->tester->grabService(SettingsStoreServiceInterface::class),
            $this->tester->grabService(IndexStatsServiceInterface::class),
            pageSize: 2,
            pageBytes: 1,
        );
        $log = new TestHandler();
        $exporter->setLogger(new Logger('test', [$log]));

        $result = $exporter->export($storage, 'paged', new ExportOptions());

        $simple = $result->manifest->getIndex('data-object_simple');
        $this->assertNotNull($simple);
        $this->assertSame(5, $simple->documentCount, 'every document is exported despite the tiny budget');

        $requested = [];
        foreach ($log->getRecords() as $record) {
            if (($record->context['index'] ?? null) === 'data-object_simple') {
                $requested[] = $record->context['requested'];
            }
        }
        // a 1-document probe, then 1 per page because the budget allows nothing more; the last
        // request returns the empty page that ends the loop
        $this->assertSame([1, 1, 1, 1, 1, 1], $requested);
    }

    public function testAnAbortedExportDirectoryIsClearedBeforeTheRetry(): void
    {
        // A directory without manifest.json is an aborted export; hasSnapshot() correctly says
        // "no snapshot", but the retry must not leave that run's files beside the new manifest,
        // where they would carry stale indexed data along when the bundle is copied.
        $this->tester->createFullyFledgedObjectSimple('snapshot-retry-', true, true, 1);
        $this->tester->flushIndex();
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $storage = new SnapshotStorage($filesystem);
        $filesystem->write('retry/data-object_gone.ndjson.gz', 'stale');
        $this->assertFalse($storage->hasSnapshot('retry'));
        /** @var SnapshotExporterInterface $exporter */
        $exporter = $this->tester->grabService(SnapshotExporterInterface::class);

        $exporter->export($storage, 'retry', new ExportOptions());

        $this->assertTrue($storage->hasSnapshot('retry'));
        $this->assertFalse(
            $filesystem->fileExists('retry/data-object_gone.ndjson.gz'),
            'stale file from the aborted run is gone',
        );
    }
}

/**
 * Delegates every SnapshotStorageInterface call to a real, in-memory-backed SnapshotStorage,
 * except the named methods in $failures, each of which throws its given exception instead of
 * delegating — from its first call, or from the call number given in $failFromCall — used to
 * exercise the exporter's failure-cleanup paths.
 */
final class DelegatingFailingSnapshotStorage implements SnapshotStorageInterface
{
    /** @var array<string, int> method name => calls seen so far */
    private array $calls = [];

    /**
     * @param array<string, Throwable> $failures method name => exception to throw instead of delegating
     * @param array<string, int> $failFromCall method name => first call (1-based) that fails; default 1
     */
    public function __construct(
        private readonly SnapshotStorageInterface $inner,
        private readonly array $failures,
        private readonly array $failFromCall = [],
    ) {
    }

    public function listSnapshots(): array
    {
        return $this->inner->listSnapshots();
    }

    public function latestSnapshotName(): ?string
    {
        return $this->inner->latestSnapshotName();
    }

    public function hasSnapshot(string $name): bool
    {
        return $this->inner->hasSnapshot($name);
    }

    public function readManifest(string $name): Manifest
    {
        return $this->inner->readManifest($name);
    }

    public function writeManifest(string $name, Manifest $manifest): void
    {
        $this->maybeFail(__FUNCTION__);
        $this->inner->writeManifest($name, $manifest);
    }

    public function writeFile(string $name, string $file, string $localPath): void
    {
        $this->maybeFail(__FUNCTION__);
        $this->inner->writeFile($name, $file, $localPath);
    }

    public function readFileToLocal(string $name, string $file, string $localPath): void
    {
        $this->inner->readFileToLocal($name, $file, $localPath);
    }

    public function deleteSnapshot(string $name): void
    {
        $this->maybeFail(__FUNCTION__);
        $this->inner->deleteSnapshot($name);
    }

    public static function assertValidName(string $name): void
    {
        SnapshotStorage::assertValidName($name);
    }

    private function maybeFail(string $method): void
    {
        $this->calls[$method] = ($this->calls[$method] ?? 0) + 1;
        if (isset($this->failures[$method]) && $this->calls[$method] >= ($this->failFromCall[$method] ?? 1)) {
            throw $this->failures[$method];
        }
    }
}
