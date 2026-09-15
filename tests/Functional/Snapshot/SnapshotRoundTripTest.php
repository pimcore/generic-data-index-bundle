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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotIncompatibleException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ExportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportedIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DataObjectTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotExporterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotImporterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorage;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Tests\Support\Util\TestHelper;

final class SnapshotRoundTripTest extends Unit
{
    protected IndexTester $tester;

    private SnapshotStorage $storage;

    private Filesystem $filesystem;

    private SearchIndexServiceInterface $searchIndexService;

    private IndexQueueRepository $queueRepository;

    private string $simpleAlias;

    protected function _before(): void
    {
        $this->tester->enableSynchronousProcessing();
        $this->tester->clearQueue();
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->storage = new SnapshotStorage($this->filesystem, 0);
        $this->searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);
        $this->queueRepository = $this->tester->grabService(IndexQueueRepository::class);
        $this->simpleAlias = $this->tester->grabService(DataObjectTypeAdapter::class)
            ->getAliasIndexName(ClassDefinition::getByName('simple'));
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    public function testExportDeleteImportRestoresIdenticalDocumentsWithoutEnqueueing(): void
    {
        $objects = [];
        for ($i = 1; $i <= 3; $i++) {
            $objects[] = $this->tester->createFullyFledgedObjectSimple('snapshot-roundtrip-', true, true, $i);
        }
        $this->tester->flushIndex();
        $originals = [];
        foreach ($objects as $object) {
            $originals[$object->getId()] = $this->tester->checkIndexEntry($object->getId(), $this->simpleAlias)['_source'];
        }
        $this->exporter()->export($this->storage, 'rt', new ExportOptions());

        // destroy the physical index so provisioning is exercised, then import
        $this->searchIndexService->deleteIndex($this->tester->getIndexName('simple', true));
        $this->assertFalse($this->searchIndexService->existsAlias($this->simpleAlias));
        $this->tester->clearQueue();
        $queueBefore = $this->queueRepository->countIndexQueueEntries();

        $result = $this->importer()->import($this->storage, 'rt', new ImportOptions());

        $this->assertTrue($result->isSuccessful(), print_r($result->imported, true));
        $this->assertSame($queueBefore, $this->queueRepository->countIndexQueueEntries(), 'import must not enqueue');
        $this->tester->flushIndex();
        foreach ($originals as $id => $source) {
            $this->assertSame($source, $this->tester->checkIndexEntry($id, $this->simpleAlias)['_source']);
        }
        // the restored index is searchable on the localized value, not just populated
        $value = $objects[0]->getLoc_name('de');
        $hits = $this->tester->getIndexSearchClient()->search([
            'index' => $this->simpleAlias,
            'body' => ['query' => ['match' => ['standard_fields.loc_name.de' => $value]]],
        ]);
        $this->assertSame($objects[0]->getId(), (int) $hits['hits']['hits'][0]['_id']);
        $this->assertSame(3, $this->searchIndexService->getCount(new Search(), $this->simpleAlias));
    }

    public function testImportRefusesOnChecksumMismatchAndWritesNothing(): void
    {
        $object = $this->tester->createFullyFledgedObjectSimple('snapshot-refuse-', true, true, 4);
        $this->tester->flushIndex();
        $this->exporter()->export($this->storage, 'bad', new ExportOptions());
        $manifest = $this->storage->readManifest('bad');
        $classId = ClassDefinition::getByName('simple')->getId();
        $this->storage->writeManifest('bad', $manifest->withClassMappingChecksums([$classId => 999]));
        $countBefore = $this->searchIndexService->getCount(new Search(), $this->simpleAlias);

        try {
            $this->importer()->import($this->storage, 'bad', new ImportOptions());
            $this->fail('expected SnapshotIncompatibleException');
        } catch (SnapshotIncompatibleException $e) {
            $this->assertSame([$classId], $e->report->incompatibleClassIds());
        }
        $this->tester->flushIndex();
        $this->assertSame($countBefore, $this->searchIndexService->getCount(new Search(), $this->simpleAlias), 'nothing was written');
        $this->tester->checkIndexEntry($object->getId(), $this->simpleAlias);
    }

    public function testForceSkipsOnlyTheIncompatibleClass(): void
    {
        $this->tester->createFullyFledgedObjectSimple('snapshot-force-', true, true, 5);
        $this->tester->flushIndex();
        $this->exporter()->export($this->storage, 'forced', new ExportOptions());
        $classId = ClassDefinition::getByName('simple')->getId();
        $this->storage->writeManifest('forced', $this->storage->readManifest('forced')->withClassMappingChecksums([$classId => 999]));

        $result = $this->importer()->import($this->storage, 'forced', new ImportOptions(force: true));

        $this->assertArrayHasKey('data-object_simple', $result->skipped);
        $importedNames = array_map(static fn (ImportedIndex $i) => $i->shortName, $result->imported);
        $this->assertContains('asset', $importedNames);
        $this->assertNotContains('data-object_simple', $importedNames);
    }

    public function testCorruptedFileIsRejectedBeforeIndexing(): void
    {
        $this->tester->createFullyFledgedObjectSimple('snapshot-corrupt-', true, true, 6);
        $this->tester->flushIndex();
        $this->exporter()->export($this->storage, 'corrupt', new ExportOptions());
        $this->filesystem->write('corrupt/data-object_simple.ndjson.gz', gzencode("{\"system_fields\":{\"id\":1}}\n"));

        $this->expectException(SnapshotImportException::class);
        $this->expectExceptionMessage('Checksum mismatch');
        $this->importer()->import($this->storage, 'corrupt', new ImportOptions(only: ['data-object_simple']));
    }

    public function testUnknownOnlyNameIsRejected(): void
    {
        $this->exporter()->export($this->storage, 'only', new ExportOptions());

        $this->expectException(SnapshotImportException::class);
        $this->importer()->import($this->storage, 'only', new ImportOptions(only: ['does_not_exist']));
    }

    private function exporter(): SnapshotExporterInterface
    {
        return $this->tester->grabService(SnapshotExporterInterface::class);
    }

    private function importer(): SnapshotImporterInterface
    {
        return $this->tester->grabService(SnapshotImporterInterface::class);
    }
}
