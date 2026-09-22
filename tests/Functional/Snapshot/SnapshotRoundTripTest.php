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
use FilesystemIterator;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotIncompatibleException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ExportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportedIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DataObjectTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DocumentTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\CompatibilityCheckerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileReader;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentFileWriter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\DocumentReplayer;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\IndexProvisionerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\ReplayIndexSettingsInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotExporterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotImporter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotImporterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotIndexResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Tests\Support\Util\TestHelper;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class SnapshotRoundTripTest extends Unit
{
    protected IndexTester $tester;

    private SnapshotStorage $storage;

    private Filesystem $filesystem;

    private SearchIndexServiceInterface $searchIndexService;

    private IndexQueueRepository $queueRepository;

    private string $simpleAlias;

    /** @var string[] directories created by a test, removed in _after() */
    private array $tempPaths = [];

    protected function _before(): void
    {
        $this->tester->enableSynchronousProcessing();
        $this->tester->clearQueue();
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->storage = new SnapshotStorage($this->filesystem);
        $this->searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);
        $this->queueRepository = $this->tester->grabService(IndexQueueRepository::class);
        $this->simpleAlias = $this->tester->grabService(DataObjectTypeAdapter::class)
            ->getAliasIndexName(ClassDefinition::getByName('simple'));
        // TestHelper::cleanUp() (in a previous test's _after()) truncates the settings store, so
        // every class definition's mapping checksum is gone even though its index still exists
        // from suite bootstrap. A real installation always has a checksum from the moment a class
        // definition is saved (IndexUpdateService::updateClassDefinition()); restamp it here so
        // export()/check() see the same state as a healthy installation, and only the class a
        // test deliberately corrupts ends up incompatible/unverified.
        $this->restampAllClassMappingChecksums();
    }

    private function restampAllClassMappingChecksums(): void
    {
        $settingsStore = $this->tester->grabService(SettingsStoreServiceInterface::class);
        $handler = $this->tester->grabService(DataObjectIndexHandler::class);
        foreach ((new ClassDefinition\Listing())->load() as $classDefinition) {
            $mappingProperties = $handler->getMappingProperties($classDefinition);
            $settingsStore->storeClassMapping($classDefinition->getId(), $handler->getClassMappingCheckSum($mappingProperties));
        }
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
        foreach ($this->tempPaths as $path) {
            if (is_dir($path)) {
                $this->removeDirectory($path);
            }
        }
        $this->tempPaths = [];
    }

    public function testExportDeleteImportRestoresIdenticalDocumentsWithoutEnqueueing(): void
    {
        $objects = [];
        for ($i = 1; $i <= 3; $i++) {
            $objects[] = $this->tester->createFullyFledgedObjectSimple('snapshot-roundtrip-', true, true, $i);
        }
        $documentAlias = $this->tester->grabService(DocumentTypeAdapter::class)->getAliasIndexName();
        $assetAlias = $this->tester->grabService(AssetTypeAdapter::class)->getAliasIndexName();
        $page = TestHelper::createEmptyDocumentPage('snapshot-doc-');
        $asset = TestHelper::createImageAsset('snapshot-asset-');
        $this->tester->flushIndex();
        $originals = [];
        foreach ($objects as $object) {
            $originals[$object->getId()] = $this->tester->checkIndexEntry($object->getId(), $this->simpleAlias)['_source'];
        }
        $originalDocumentSource = $this->tester->checkIndexEntry($page->getId(), $documentAlias)['_source'];
        $originalAssetSource = $this->tester->checkIndexEntry($asset->getId(), $assetAlias)['_source'];
        $this->exporter()->export($this->storage, 'rt', new ExportOptions());

        // destroy the physical indices so provisioning is exercised, then import
        $this->searchIndexService->deleteIndex($this->tester->getIndexName('simple', true));
        $this->searchIndexService->deleteIndex($this->tester->getIndexName('document'));
        $this->searchIndexService->deleteIndex($this->tester->getIndexName('asset'));
        $this->assertFalse($this->searchIndexService->existsAlias($this->simpleAlias));
        $this->assertFalse($this->searchIndexService->existsAlias($documentAlias));
        $this->assertFalse($this->searchIndexService->existsAlias($assetAlias));
        $this->tester->clearQueue();
        $queueBefore = $this->queueRepository->countIndexQueueEntries();

        $result = $this->importer()->import($this->storage, 'rt', new ImportOptions());

        $this->assertTrue($result->isSuccessful(), print_r($result->imported, true));
        $this->assertSame($queueBefore, $this->queueRepository->countIndexQueueEntries(), 'import must not enqueue');
        $this->tester->flushIndex();
        foreach ($originals as $id => $source) {
            $this->assertSame($source, $this->tester->checkIndexEntry($id, $this->simpleAlias)['_source']);
        }
        $this->assertSame(
            $originalDocumentSource,
            $this->tester->checkIndexEntry($page->getId(), $documentAlias)['_source'],
        );
        $this->assertSame(
            $originalAssetSource,
            $this->tester->checkIndexEntry($asset->getId(), $assetAlias)['_source'],
            'the asset index is provisioned and replayed like every other index',
        );
        // the restored index is searchable on the localized value, not just populated
        $value = $objects[0]->getLoc_name('de');
        $hits = $this->tester->getIndexSearchClient()->search([
            'index' => $this->simpleAlias,
            'body' => ['query' => ['match' => ['standard_fields.loc_name.de' => $value]]],
        ]);
        $this->assertCount(1, $hits['hits']['hits']);
        $this->assertSame($objects[0]->getId(), (int) $hits['hits']['hits'][0]['_id']);
        $this->assertSame(3, $this->searchIndexService->getCount(new Search(), $this->simpleAlias));
        // the replay runs with refresh disabled and an asynchronous translog; both must be back
        // at the engine defaults once the index is imported
        $settings = $this->tester->getIndexSearchClient()->getIndexSettings(['index' => $this->simpleAlias]);
        $index = array_values($settings)[0]['settings']['index'];
        $this->assertArrayNotHasKey('refresh_interval', $index, 'refresh_interval restored to default');
        $this->assertArrayNotHasKey('translog', $index, 'translog durability restored to default');
    }

    public function testImportRefusesOnChecksumMismatchAndWritesNothing(): void
    {
        $object = $this->tester->createFullyFledgedObjectSimple('snapshot-refuse-', true, true, 4);
        $this->tester->flushIndex();
        $this->exporter()->export($this->storage, 'bad', new ExportOptions());
        $manifest = $this->storage->readManifest('bad');
        $classId = ClassDefinition::getByName('simple')->getId();
        // Override only "simple"'s checksum, keep the others: withClassMappingChecksums() replaces
        // the whole map, and dropping the other classes' entries entirely would make them
        // UNVERIFIED too (a different, unrelated failure mode from the one under test here).
        $this->storage->writeManifest('bad', $manifest->withClassMappingChecksums([...$manifest->classMappingChecksums, $classId => 999]));
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
        $object = $this->tester->createFullyFledgedObjectSimple('snapshot-force-', true, true, 5);
        $this->tester->flushIndex();
        $this->exporter()->export($this->storage, 'forced', new ExportOptions());
        $classId = ClassDefinition::getByName('simple')->getId();
        $manifest = $this->storage->readManifest('forced');
        // Override only "simple"'s checksum, keep the others (see comment in
        // testImportRefusesOnChecksumMismatchAndWritesNothing() above).
        $this->storage->writeManifest('forced', $manifest->withClassMappingChecksums([...$manifest->classMappingChecksums, $classId => 999]));

        $result = $this->importer()->import($this->storage, 'forced', new ImportOptions(force: true));

        $this->assertArrayHasKey('data-object_simple', $result->skipped);
        $this->assertCount(1, $result->skipped);
        $this->assertStringContainsString('incompatible', $result->skipped['data-object_simple']);
        $importedNames = array_map(static fn (ImportedIndex $i) => $i->shortName, $result->imported);
        $this->assertContains('asset', $importedNames);
        $this->assertNotContains('data-object_simple', $importedNames);
        // --force must never provision/replay the skipped index: the pre-existing object is untouched
        $this->tester->checkIndexEntry($object->getId(), $this->simpleAlias);
    }

    public function testCorruptedFileIsRejectedBeforeIndexing(): void
    {
        $object = $this->tester->createFullyFledgedObjectSimple('snapshot-corrupt-', true, true, 6);
        $this->tester->flushIndex();
        $this->exporter()->export($this->storage, 'corrupt', new ExportOptions());
        $classId = ClassDefinition::getByName('simple')->getId();
        $settingsStore = $this->tester->grabService(SettingsStoreServiceInterface::class);
        $checksumBefore = $settingsStore->getClassMappingCheckSum($classId);
        $this->filesystem->write('corrupt/data-object_simple.ndjson.gz', gzencode("{\"system_fields\":{\"id\":1}}\n"));

        try {
            $this->importer()->import($this->storage, 'corrupt', new ImportOptions());
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString('data-object_simple', $e->getMessage());
            // the size check runs first and already rejects the corrupted content
            $this->assertStringContainsString('mismatch', $e->getMessage());
        }
        // the checksum is verified before the live index is touched: it must still be intact
        $this->assertTrue($this->searchIndexService->existsAlias($this->simpleAlias));
        $this->tester->checkIndexEntry($object->getId(), $this->simpleAlias);
        $this->assertSame($checksumBefore, $settingsStore->getClassMappingCheckSum($classId), 'a rejected import must not stamp the class mapping checksum');
    }

    public function testCorruptFileLateInThePlanLeavesAllIndicesUntouched(): void
    {
        // Preflight verifies every planned file BEFORE provisioning any index. Corrupting the
        // LAST entry in the manifest must still be caught before the FIRST entry (already
        // downloaded and verified during preflight) is ever provisioned/touched.
        $this->tester->createFullyFledgedObjectSimple('snapshot-preflight-', true, true, 30);
        $this->tester->flushIndex();
        $this->exporter()->export($this->storage, 'preflight', new ExportOptions());

        $manifest = $this->storage->readManifest('preflight');
        $this->assertGreaterThan(1, count($manifest->indices), 'need at least two planned indices to prove ordering');
        $resolver = $this->tester->grabService(SnapshotIndexResolverInterface::class);
        $firstEntry = $manifest->indices[0];
        $lastEntry = $manifest->indices[count($manifest->indices) - 1];
        $firstTarget = $resolver->resolveManifestIndex($firstEntry);
        $this->assertNotNull($firstTarget);
        $firstAlias = $firstTarget->aliasName;

        $countBefore = $this->searchIndexService->getCount(new Search(), $firstAlias);
        $versionBefore = $this->searchIndexService->getCurrentIndexVersion($firstAlias);

        $this->filesystem->write('preflight/' . $lastEntry->file, gzencode("{\"system_fields\":{\"id\":1}}\n"));
        $tempDir = DocumentFileWriter::temporaryDirectory();
        $tempFilesBefore = glob($tempDir . '/gdi-snapshot-in-*') ?: [];

        try {
            $this->importer()->import($this->storage, 'preflight', new ImportOptions());
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            // the size check runs first and already rejects the corrupted content
            $this->assertStringContainsString('mismatch', $e->getMessage());
        }

        $this->tester->flushIndex();
        $this->assertSame(
            $countBefore,
            $this->searchIndexService->getCount(new Search(), $firstAlias),
            'the first planned index must still hold its documents',
        );
        $this->assertSame(
            $versionBefore,
            $this->searchIndexService->getCurrentIndexVersion($firstAlias),
            'the first planned index must not have been provisioned (recreated)',
        );

        $tempFilesAfter = glob($tempDir . '/gdi-snapshot-in-*') ?: [];
        $this->assertSame(
            [],
            array_diff($tempFilesAfter, $tempFilesBefore),
            'no leftover snapshot import temp files after a preflight failure',
        );
    }

    public function testFailedReplayDoesNotStampClassMappingChecksum(): void
    {
        // Unlike testCorruptedFileIsRejectedBeforeIndexing() above, where the checksum verification
        // itself fails before the index is ever touched, this reaches the real regression: the
        // index is provisioned (recreated with the current mapping) and only THEN does replaying
        // the documents fail, because the file's checksum is valid but its content isn't. Stamping
        // the class mapping checksum during provisioning (the old behavior) would then mark the
        // mapping as current even though the index ends up empty.
        $this->tester->createFullyFledgedObjectSimple('snapshot-stamp-fail-', true, true, 11);
        $this->tester->flushIndex();
        $this->exporter()->export($this->storage, 'stamp-fail', new ExportOptions());
        $classId = ClassDefinition::getByName('simple')->getId();
        $settingsStore = $this->tester->grabService(SettingsStoreServiceInterface::class);
        $checksumBefore = $settingsStore->getClassMappingCheckSum($classId);
        $this->assertNotNull($checksumBefore);

        $badContent = gzencode("{\"standard_fields\":{}}\n");
        $this->filesystem->write('stamp-fail/data-object_simple.ndjson.gz', $badContent);
        $manifest = $this->storage->readManifest('stamp-fail');
        $entry = $manifest->getIndex('data-object_simple');
        $fixedEntry = new ManifestIndex(
            $entry->shortName, $entry->elementType, $entry->classId, $entry->sourceIndex,
            $entry->documentCount, $entry->file, strlen($badContent), hash('sha256', $badContent)
        );
        $this->storage->writeManifest('stamp-fail', $manifest->withIndices(
            array_map(static fn (ManifestIndex $i) => $i->shortName === 'data-object_simple' ? $fixedEntry : $i, $manifest->indices)
        ));

        try {
            $this->importer()->import($this->storage, 'stamp-fail', new ImportOptions());
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString('Document without integer system_fields.id', $e->getMessage());
        }
        $this->assertNull(
            $settingsStore->getClassMappingCheckSum($classId),
            'the checksum is removed before the destructive recreation and never re-stamped after a failed replay',
        );
        // ... which is exactly what lets GDI's own per-class reindex repair the now-empty index:
        // with a stored checksum equal to the current one it would skip the class as unchanged.
        $reindexService = $this->tester->grabService(ClassDefinitionReindexServiceInterface::class);
        $this->assertTrue(
            $reindexService->reindexClassDefinition(ClassDefinition::getByName('simple'), true),
            'the per-class reindex must not skip a class whose index was emptied by a failed import',
        );
    }

    public function testIncompleteReplayDoesNotStampClassMappingChecksum(): void
    {
        // The replay itself succeeds (the file's checksum and contents are valid), but the
        // manifest's declared document count no longer matches what was actually replayed -
        // simulating a manifest that was hand-edited, or a source index that changed between
        // export and import. This must abort the import like any other replay failure: stamping
        // the checksum here would mark the mapping "current" even though the index is short a
        // document, and self-healing would never kick in.
        //
        // Note: coverage for an index later in the plan staying untouched by the abort lives in
        // testManifestEntryCannotReferenceAnotherIndexFile() and
        // testCorruptFileLateInThePlanLeavesAllIndicesUntouched() instead.
        for ($i = 1; $i <= 2; $i++) {
            $this->tester->createFullyFledgedObjectSimple('snapshot-incomplete-', true, true, 20 + $i);
        }
        $this->tester->flushIndex();
        $this->exporter()->export($this->storage, 'incomplete', new ExportOptions());
        $classId = ClassDefinition::getByName('simple')->getId();
        $settingsStore = $this->tester->grabService(SettingsStoreServiceInterface::class);
        $checksumBefore = $settingsStore->getClassMappingCheckSum($classId);
        $this->assertNotNull($checksumBefore);

        $manifest = $this->storage->readManifest('incomplete');
        $entry = $manifest->getIndex('data-object_simple');
        $bumpedEntry = new ManifestIndex(
            $entry->shortName, $entry->elementType, $entry->classId, $entry->sourceIndex,
            3, $entry->file, $entry->bytes, $entry->sha256
        );
        $this->storage->writeManifest('incomplete', $manifest->withIndices(
            array_map(static fn (ManifestIndex $i) => $i->shortName === 'data-object_simple' ? $bumpedEntry : $i, $manifest->indices)
        ));
        $settingsStore->storeClassMapping($classId, 424242);

        try {
            $this->importer()->import($this->storage, 'incomplete', new ImportOptions());
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString($this->simpleAlias, $e->getMessage(), 'expected the message to name the index alias');
            $this->assertStringContainsString('2/3', $e->getMessage(), 'expected the message to name the actual and expected counts');
            $this->assertNull(
                $settingsStore->getClassMappingCheckSum($classId),
                'an incomplete replay must leave no checksum, so the per-class reindex repairs the index',
            );
        } finally {
            $settingsStore->storeClassMapping($classId, $checksumBefore);
        }
    }

    public function testInvalidManifestFileNameIsRejected(): void
    {
        // A '../etc/passwd' file name no longer even matches "<short_name>.ndjson.gz", so this
        // is now caught by Manifest::fromArray() when the tampered manifest is read back, before
        // the importer's own file-name regex guard is ever reached.
        $object = $this->tester->createFullyFledgedObjectSimple('snapshot-badname-', true, true, 12);
        $this->tester->flushIndex();
        $this->exporter()->export($this->storage, 'badname', new ExportOptions());
        $manifest = $this->storage->readManifest('badname');
        $entry = $manifest->getIndex('data-object_simple');
        $tampered = new ManifestIndex(
            $entry->shortName, $entry->elementType, $entry->classId, $entry->sourceIndex,
            $entry->documentCount, '../etc/passwd', $entry->bytes, $entry->sha256
        );
        $this->storage->writeManifest('badname', $manifest->withIndices(
            array_map(static fn (ManifestIndex $i) => $i->shortName === 'data-object_simple' ? $tampered : $i, $manifest->indices)
        ));

        try {
            $this->importer()->import($this->storage, 'badname', new ImportOptions());
            $this->fail('expected InvalidSnapshotException');
        } catch (InvalidSnapshotException $e) {
            $this->assertStringContainsString('must match its "short_name"', $e->getMessage());
        }
        $this->tester->checkIndexEntry($object->getId(), $this->simpleAlias);
    }

    public function testManifestEntryCannotReferenceAnotherIndexFile(): void
    {
        // A manifest entry naming another index's (valid) file, with that file's real sha256, must
        // not be accepted just because the file/hash pair checks out: the asset entry must be
        // bound to its own "asset.ndjson.gz", not able to borrow "data-object_simple.ndjson.gz".
        $object = $this->tester->createFullyFledgedObjectSimple('snapshot-crossref-', true, true, 14);
        $this->tester->flushIndex();
        $this->exporter()->export($this->storage, 'crossref', new ExportOptions());
        $manifest = $this->storage->readManifest('crossref');
        $dataObjectEntry = $manifest->getIndex('data-object_simple');
        $assetEntry = $manifest->getIndex('asset');
        $this->assertNotNull($dataObjectEntry);
        $this->assertNotNull($assetEntry);
        $assetAlias = $this->tester->grabService(AssetTypeAdapter::class)->getAliasIndexName();
        $assetCountBefore = $this->searchIndexService->getCount(new Search(), $assetAlias);

        $rewrittenAssetEntry = new ManifestIndex(
            $assetEntry->shortName,
            $assetEntry->elementType,
            $assetEntry->classId,
            $assetEntry->sourceIndex,
            $dataObjectEntry->documentCount,
            $dataObjectEntry->file,
            $dataObjectEntry->bytes,
            $dataObjectEntry->sha256,
        );
        $this->storage->writeManifest('crossref', $manifest->withIndices(
            array_map(
                static fn (ManifestIndex $i) => $i->shortName === 'asset' ? $rewrittenAssetEntry : $i,
                $manifest->indices,
            )
        ));

        try {
            $this->importer()->import($this->storage, 'crossref', new ImportOptions());
            $this->fail('expected InvalidSnapshotException or SnapshotImportException');
        } catch (InvalidSnapshotException|SnapshotImportException $e) {
            $this->assertStringContainsString('asset', $e->getMessage());
        }
        $this->tester->flushIndex();
        $this->assertSame(
            $assetCountBefore,
            $this->searchIndexService->getCount(new Search(), $assetAlias),
            'the asset index must be untouched',
        );
        $this->tester->checkIndexEntry($object->getId(), $this->simpleAlias);
    }

    public function testMissingChecksumInManifestIsUnverifiedAndGatesUnlessForced(): void
    {
        $object = $this->tester->createFullyFledgedObjectSimple('snapshot-unverified-', true, true, 13);
        $this->tester->flushIndex();
        $this->exporter()->export($this->storage, 'unverified', new ExportOptions());
        $manifest = $this->storage->readManifest('unverified');
        $this->storage->writeManifest('unverified', $manifest->withClassMappingChecksums([]));

        try {
            $this->importer()->import($this->storage, 'unverified', new ImportOptions());
            $this->fail('expected SnapshotIncompatibleException');
        } catch (SnapshotIncompatibleException) {
            // expected: a class index without a manifest checksum is UNVERIFIED, which gates the
            // import the same way an INCOMPATIBLE checksum would
        }

        $result = $this->importer()->import($this->storage, 'unverified', new ImportOptions(force: true));

        $this->assertArrayHasKey('data-object_simple', $result->skipped);
        $this->assertStringContainsString('no class mapping checksum', $result->skipped['data-object_simple']);
        $this->tester->checkIndexEntry($object->getId(), $this->simpleAlias);
    }

    public function testDryRunIsSuccessfulAndWritesNothing(): void
    {
        $this->tester->createFullyFledgedObjectSimple('snapshot-dry-', true, true, 7);
        $this->tester->flushIndex();
        $this->exporter()->export($this->storage, 'dry', new ExportOptions());
        $countBefore = $this->searchIndexService->getCount(new Search(), $this->simpleAlias);

        $result = $this->importer()->import($this->storage, 'dry', new ImportOptions(dryRun: true));

        $this->assertTrue($result->dryRun);
        $this->assertTrue($result->isSuccessful(), 'a dry run plan has no counts to compare and is always successful');
        $this->tester->flushIndex();
        $this->assertSame($countBefore, $this->searchIndexService->getCount(new Search(), $this->simpleAlias), 'dry run writes nothing');
    }

    public function testExportCommandRunsEndToEnd(): void
    {
        $this->tester->createFullyFledgedObjectSimple('snapshot-cmd-', true, true, 8);
        $this->tester->flushIndex();

        $output = $this->tester->runConsoleCommand('generic-data-index:snapshot:export', ['--name' => 'cmd-test', '--dry-run' => true]);

        $this->assertStringContainsString('data-object_simple', $output);
        $this->assertStringContainsString('Nothing written', $output);
    }

    public function testImportCommandRunsEndToEndFromLocalPath(): void
    {
        $object = $this->tester->createFullyFledgedObjectSimple('snapshot-import-cmd-', true, true, 9);
        $this->tester->flushIndex();
        $dir = sys_get_temp_dir() . '/gdi-snapshot-func-' . uniqid();
        $this->tempPaths[] = $dir;
        mkdir($dir, 0777, true);
        $localStorage = new SnapshotStorage(new Filesystem(new LocalFilesystemAdapter($dir)));
        $this->exporter()->export($localStorage, 'local', new ExportOptions());
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();

        $output = $this->tester->runConsoleCommand('generic-data-index:snapshot:import', ['name' => 'local', '--from-path' => $dir]);

        $this->assertStringContainsString('Snapshot imported', $output);
        $this->tester->flushIndex();
        $this->tester->checkIndexEntry($object->getId(), $this->simpleAlias);
    }

    private function exporter(): SnapshotExporterInterface
    {
        return $this->tester->grabService(SnapshotExporterInterface::class);
    }

    private function importer(): SnapshotImporterInterface
    {
        return $this->tester->grabService(SnapshotImporterInterface::class);
    }

    private function removeDirectory(string $dir): void
    {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    public function testBulkRequestsAreFlushedByByteBudgetAndStillRestoreEveryDocument(): void
    {
        $objects = [];
        for ($i = 1; $i <= 3; $i++) {
            $objects[] = $this->tester->createFullyFledgedObjectSimple('snapshot-bulkbytes-', true, true, $i);
        }
        $this->tester->flushIndex();
        $this->exporter()->export($this->storage, 'bb', new ExportOptions());
        $this->searchIndexService->deleteIndex($this->tester->getIndexName('simple', true));

        // ceiling of 1000 documents, budget of 1 byte: every document must be flushed on its own
        $replayer = new DocumentReplayer(
            $this->tester->grabService('generic-data-index.search-client'),
            new DocumentFileReader(),
            bulkSize: 1000,
            bulkBytes: 1,
        );
        $log = new TestHandler();
        $replayer->setLogger(new Logger('test', [$log]));
        $importer = new SnapshotImporter(
            $this->tester->grabService(SnapshotIndexResolverInterface::class),
            $this->tester->grabService(CompatibilityCheckerInterface::class),
            $this->tester->grabService(SearchIndexConfigServiceInterface::class),
            $this->searchIndexService,
            $this->tester->grabService(IndexProvisionerInterface::class),
            new DocumentFileReader(),
            $replayer,
            $this->tester->grabService(ReplayIndexSettingsInterface::class),
        );
        $importer->setLogger(new Logger('test', [$log]));

        $result = $importer->import($this->storage, 'bb', new ImportOptions());

        $this->assertTrue($result->isSuccessful(), print_r($result->imported, true));
        $this->tester->flushIndex();
        $this->assertSame(3, $this->searchIndexService->getCount(new Search(), $this->simpleAlias));
        $flushes = array_filter(
            $log->getRecords(),
            static fn ($record) => $record->message === 'Snapshot import bulk'
                && ($record->context['index'] ?? null) === 'data-object_simple',
        );
        $this->assertCount(3, $flushes, 'one bulk request per document when the byte budget is 1');
        foreach ($flushes as $flush) {
            $this->assertSame(1, $flush->context['documents']);
            $this->assertGreaterThan(1, $flush->context['bytes'], 'the first document already exceeds the budget');
        }
    }

    public function testFileSizeMismatchIsRejectedBeforeIndexing(): void
    {
        // A file whose size differs from the manifest is rejected in preflight, like a checksum
        // mismatch, before any live index is touched; the size check is the cheaper of the two.
        $object = $this->tester->createFullyFledgedObjectSimple('snapshot-size-', true, true, 7);
        $this->tester->flushIndex();
        $this->exporter()->export($this->storage, 'size', new ExportOptions());
        $manifest = $this->storage->readManifest('size');
        $entry = $manifest->getIndex('data-object_simple');
        $tampered = new ManifestIndex(
            $entry->shortName, $entry->elementType, $entry->classId, $entry->sourceIndex,
            $entry->documentCount, $entry->file, $entry->bytes + 1, $entry->sha256,
        );
        $this->storage->writeManifest('size', $manifest->withIndices(array_map(
            static fn (ManifestIndex $i) => $i->shortName === 'data-object_simple' ? $tampered : $i,
            $manifest->indices,
        )));

        try {
            $this->importer()->import($this->storage, 'size', new ImportOptions());
            $this->fail('expected SnapshotImportException');
        } catch (SnapshotImportException $e) {
            $this->assertStringContainsString('data-object_simple', $e->getMessage());
            $this->assertStringContainsString('Size mismatch', $e->getMessage());
        }
        $this->assertTrue($this->searchIndexService->existsAlias($this->simpleAlias));
        $this->tester->checkIndexEntry($object->getId(), $this->simpleAlias);
    }

    public function testAnIndexAbsentOnTheSourceIsExportedEmptyAndReplacesAStaleLocalIndex(): void
    {
        // Source installation without a document index: the snapshot must still carry the
        // document index (empty), so that importing it into an installation that does have
        // documents indexed replaces those stale documents instead of silently keeping them.
        $documentAlias = $this->tester->grabService(DocumentTypeAdapter::class)->getAliasIndexName();
        $this->searchIndexService->deleteIndex($this->tester->getIndexName('document'));
        $this->assertFalse($this->searchIndexService->existsAlias($documentAlias));

        $seen = [];
        $result = $this->exporter()->export(
            $this->storage,
            'no-documents',
            new ExportOptions(),
            static function ($target, int $count) use (&$seen): void {
                $seen[$target->shortName] = $count;
            },
        );

        $document = $result->manifest->getIndex('document');
        $this->assertNotNull($document, 'an index the source does not have is exported as an empty index');
        $this->assertSame(0, $document->documentCount);
        $this->assertSame(0, $seen['document']);
        $this->assertTrue($this->filesystem->fileExists('no-documents/' . $document->file));

        // "local" installation with a stale document in its index
        $page = TestHelper::createEmptyDocumentPage('snapshot-stale-doc-');
        $this->tester->flushIndex();
        $this->assertSame(1, $this->searchIndexService->getCount(new Search(), $documentAlias));

        $imported = $this->importer()->import($this->storage, 'no-documents', new ImportOptions());

        $this->assertTrue($imported->isSuccessful(), print_r($imported->imported, true));
        $this->tester->flushIndex();
        $this->assertTrue($this->searchIndexService->existsAlias($documentAlias));
        $this->assertSame(
            0,
            $this->searchIndexService->getCount(new Search(), $documentAlias),
            'the stale local document was replaced by the (empty) source state',
        );
        $this->assertNotNull($page->getId());
    }
}
