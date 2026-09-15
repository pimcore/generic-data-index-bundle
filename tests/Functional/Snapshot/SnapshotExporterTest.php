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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotExportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ExportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileReader;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotExporterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorage;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Db;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Tests\Support\Util\TestHelper;

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
        $storage = new SnapshotStorage(new Filesystem(new InMemoryFilesystemAdapter()), 0);
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
        $documents = iterator_to_array((new DocumentFileReader())->read($local), false);
        $this->assertCount(3, $documents);
        $ids = array_map(static fn (array $d) => $d['system_fields']['id'], $documents);
        $this->assertEqualsCanonicalizing(array_map(static fn ($o) => $o->getId(), $objects), $ids);
        $this->assertSame($objects[0]->getLoc_name('de'), $documents[array_search($objects[0]->getId(), $ids, true)]['standard_fields']['loc_name']['de']);
        unlink($local);
    }

    public function testDryRunWritesNothing(): void
    {
        $storage = new SnapshotStorage(new Filesystem(new InMemoryFilesystemAdapter()), 0);
        /** @var SnapshotExporterInterface $exporter */
        $exporter = $this->tester->grabService(SnapshotExporterInterface::class);

        $result = $exporter->export($storage, 'dry', new ExportOptions(dryRun: true));

        $this->assertTrue($result->dryRun);
        $this->assertSame([], $storage->listSnapshots());
    }

    public function testQueueThresholdRefusesAndLeavesNoDirectory(): void
    {
        $this->tester->createFullyFledgedObjectSimple('snapshot-gate-', true, true, 9);
        Db::get()->executeStatement(
            'INSERT INTO generic_data_index_queue (elementId, elementType, elementIndexName, operation, operationTime, dispatched) VALUES (999999, "dataObject", "simple", "update", 1, 0)'
        );
        $storage = new SnapshotStorage(new Filesystem(new InMemoryFilesystemAdapter()), 0);
        /** @var SnapshotExporterInterface $exporter */
        $exporter = $this->tester->grabService(SnapshotExporterInterface::class);

        try {
            $exporter->export($storage, 'gated', new ExportOptions(maxQueueEntries: 0, waitSeconds: 0));
            $this->fail('expected SnapshotExportException');
        } catch (SnapshotExportException) {
        }
        $this->assertSame([], $storage->listSnapshots());
        $this->tester->clearQueue();
    }
}
