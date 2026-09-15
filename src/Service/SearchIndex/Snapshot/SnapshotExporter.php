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

use Composer\InstalledVersions;
use DateTimeImmutable;
use DateTimeZone;
use OutOfBoundsException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotExportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSortList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ExportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ExportResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\Manifest;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\WrittenFile;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Version;
use Throwable;

/**
 * @internal
 */
final class SnapshotExporter implements SnapshotExporterInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly SearchIndexServiceInterface $searchIndexService,
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        private readonly SnapshotIndexResolverInterface $indexResolver,
        private readonly SettingsStoreServiceInterface $settingsStoreService,
        private readonly QueueGate $queueGate,
        private readonly int $pageSize,
    ) {
    }

    public function export(SnapshotStorageInterface $storage, string $name, ExportOptions $options, ?callable $onIndexExported = null): ExportResult
    {
        SnapshotStorage::assertValidName($name);
        if ($storage->hasSnapshot($name)) {
            throw new SnapshotExportException(sprintf('Snapshot "%s" already exists', $name));
        }
        $started = microtime(true);
        $queueBefore = $this->queueGate->await($options->maxQueueEntries, $options->waitSeconds);

        $targets = array_values(array_filter(
            $this->indexResolver->resolveAll(),
            fn (IndexTarget $target) => $this->searchIndexService->existsAlias($target->aliasName)
        ));

        $checksums = [];
        foreach ($targets as $target) {
            if ($target->isClassIndex()) {
                $checksum = $this->settingsStoreService->getClassMappingCheckSum($target->getClassId());
                if ($checksum !== null) {
                    $checksums[$target->getClassId()] = $checksum;
                }
            }
        }

        $manifest = new Manifest(
            createdAt: (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
            genericDataIndexVersion: $this->bundleVersion(),
            pimcoreVersion: Version::getVersion(),
            clientType: $this->searchIndexConfigService->getClientType(),
            indexPrefix: $this->searchIndexConfigService->getIndexPrefix(),
            queueEntriesBefore: $queueBefore,
            queueEntriesAfter: $queueBefore,
            durationSeconds: 0,
            classMappingChecksums: $checksums,
            indices: [],
        );

        if ($options->dryRun) {
            $indices = [];
            foreach ($targets as $target) {
                $indices[] = $this->manifestIndex($target, $this->searchIndexService->getCount(new Search(), $target->aliasName), 0, '');
            }

            return new ExportResult($name, $manifest->withIndices($indices), [], true);
        }

        $indices = [];

        try {
            foreach ($targets as $target) {
                $written = $this->exportIndex($target);

                try {
                    $storage->writeFile($name, $target->shortName . '.ndjson.gz', $written->path);
                } finally {
                    @unlink($written->path);
                }
                $indices[] = $this->manifestIndex($target, $written->documentCount, $written->bytes, $written->sha256);
                if ($onIndexExported !== null) {
                    $onIndexExported($target, $written->documentCount);
                }
            }

            $manifest = new Manifest(
                createdAt: $manifest->createdAt,
                genericDataIndexVersion: $manifest->genericDataIndexVersion,
                pimcoreVersion: $manifest->pimcoreVersion,
                clientType: $manifest->clientType,
                indexPrefix: $manifest->indexPrefix,
                queueEntriesBefore: $queueBefore,
                queueEntriesAfter: $this->queueGate->count(),
                durationSeconds: (int) round(microtime(true) - $started),
                classMappingChecksums: $checksums,
                indices: $indices,
            );
            // writeManifest() must stay inside this try: any failure here (Flysystem write error,
            // JsonException) must still delete the partial directory and surface as
            // SnapshotExportException, same as an index export failure.
            $storage->writeManifest($name, $manifest);
        } catch (Throwable $e) {
            $storage->deleteSnapshot($name);

            throw new SnapshotExportException(sprintf('Snapshot export "%s" aborted: %s', $name, $e->getMessage()), 0, $e);
        }
        $this->logger?->info(sprintf('Index snapshot "%s" written: %d indices in %d s', $name, count($indices), $manifest->durationSeconds));

        try {
            $deleted = $storage->rotate();
        } catch (Throwable $e) {
            $this->logger?->warning(sprintf('Index snapshot "%s" rotation failed: %s', $name, $e->getMessage()));

            return new ExportResult($name, $manifest, [], false, $e->getMessage());
        }

        return new ExportResult($name, $manifest, $deleted, false);
    }

    private function exportIndex(IndexTarget $target): WrittenFile
    {
        $writer = DocumentFileWriter::createTemporary();

        try {
            $search = new Search(size: $this->pageSize, source: true);
            $search->setSortList(new FieldSortList([new FieldSort(SystemField::ID->getPath())]));
            $searchAfter = null;
            do {
                $search->setSearchAfter($searchAfter);
                // `false` is not usable here: with track_total_hits disabled the search engine
                // omits "hits.total" entirely, but SearchResultDenormalizer::denormalize()
                // unconditionally reads $searchResult['hits']['total']['value'] and throws.
                // An integer bound isn't used either, because the total count is irrelevant to
                // search_after pagination (it only compares hit-count to page size).
                $result = $this->searchIndexService->search($search, $target->aliasName, true);
                $hits = $result->getHits();
                foreach ($hits as $hit) {
                    $writer->write($hit->getSource());
                }
                $lastHit = $result->getLastHit();
                $searchAfter = $lastHit?->getSort();
            } while ($lastHit !== null && $searchAfter !== null && count($hits) === $this->pageSize);

            return $writer->finish();
        } catch (Throwable $e) {
            $writer->abort();

            throw $e;
        }
    }

    private function manifestIndex(IndexTarget $target, int $documentCount, int $bytes, string $sha256): ManifestIndex
    {
        $version = $this->searchIndexService->getCurrentIndexVersion($target->aliasName);

        return new ManifestIndex(
            shortName: $target->shortName,
            elementType: $target->elementType,
            classId: $target->getClassId(),
            sourceIndex: $target->aliasName . ($version !== '' ? '-' . $version : ''),
            documentCount: $documentCount,
            file: $target->shortName . '.ndjson.gz',
            bytes: $bytes,
            sha256: $sha256,
        );
    }

    private function bundleVersion(): string
    {
        try {
            return InstalledVersions::getPrettyVersion('pimcore/generic-data-index-bundle') ?? 'dev';
        } catch (OutOfBoundsException) {
            return 'dev';
        }
    }
}
