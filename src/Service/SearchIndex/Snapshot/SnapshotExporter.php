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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexStatsServiceInterface;
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
        private readonly IndexStatsServiceInterface $indexStatsService,
        private readonly int $pageSize,
        private readonly int $pageBytes,
    ) {
    }

    public function export(
        SnapshotStorageInterface $storage,
        string $name,
        ExportOptions $options,
        ?callable $onIndexExported = null,
    ): ExportResult {
        SnapshotStorage::assertValidName($name);
        if ($storage->hasSnapshot($name)) {
            throw new SnapshotExportException(sprintf('Snapshot "%s" already exists', $name));
        }
        $started = microtime(true);
        $queueBefore = $this->queueCount();

        $targets = $this->indexResolver->resolveAll();
        $checksums = $this->collectClassChecksums($targets);

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

        if ($options->isDryRun()) {
            $indices = [];
            foreach ($targets as $target) {
                $count = $this->searchIndexService->existsAlias($target->getAliasName())
                    ? $this->searchIndexService->getCount(new Search(), $target->getAliasName())
                    : 0;
                $indices[] = $this->manifestIndex($target, $count, 0, '');
            }

            return new ExportResult($name, $manifest->withIndices($indices), true);
        }

        $this->clearAbortedRun($storage, $name);

        try {
            $indices = $this->exportAllIndices($storage, $name, $targets, $onIndexExported);

            $manifest = new Manifest(
                createdAt: $manifest->getCreatedAt(),
                genericDataIndexVersion: $manifest->getGenericDataIndexVersion(),
                pimcoreVersion: $manifest->getPimcoreVersion(),
                clientType: $manifest->getClientType(),
                indexPrefix: $manifest->getIndexPrefix(),
                queueEntriesBefore: $queueBefore,
                queueEntriesAfter: $this->queueCount(),
                durationSeconds: (int) round(microtime(true) - $started),
                classMappingChecksums: $checksums,
                indices: $indices,
            );
            // writeManifest() must stay inside this try: any failure here (Flysystem write error,
            // JsonException) must still delete the partial directory and surface as
            // SnapshotExportException, same as an index export failure.
            $storage->writeManifest($name, $manifest);
        } catch (Throwable $e) {
            // The cleanup delete can itself fail (e.g. the same storage outage that caused the
            // original failure). Don't let that mask the original error: report both, but keep
            // the original exception as the cause.
            $cleanupMessage = '';

            try {
                $storage->deleteSnapshot($name);
            } catch (Throwable $cleanupError) {
                $cleanupMessage = ' Cleanup of the partial snapshot also failed: ' . $cleanupError->getMessage();
            }

            throw new SnapshotExportException(
                sprintf('Snapshot export "%s" aborted: %s', $name, $e->getMessage()) . $cleanupMessage,
                0,
                $e,
            );
        }
        $this->logger?->info(sprintf(
            'Index snapshot "%s" written: %d indices in %d s',
            $name,
            count($indices),
            $manifest->getDurationSeconds(),
        ));

        return new ExportResult($name, $manifest, false);
    }

    /**
     * hasSnapshot() only looks for the manifest, so a directory left behind by an aborted run (no
     * manifest, some index files) is not "an existing snapshot" and must not block a retry. It
     * must not be reused as-is either: files of indices the retry does not write again would sit
     * beside the new manifest and travel along with the bundle. deleteSnapshot() is silent when
     * there is nothing to remove.
     *
     * @throws SnapshotExportException
     */
    private function clearAbortedRun(SnapshotStorageInterface $storage, string $name): void
    {
        try {
            $storage->deleteSnapshot($name);
        } catch (Throwable $e) {
            throw new SnapshotExportException(
                sprintf('Cannot clear the leftovers of an aborted export "%s": %s', $name, $e->getMessage()),
                0,
                $e,
            );
        }
    }

    private function queueCount(): int
    {
        return $this->indexStatsService->getStats()->getCountIndexQueueEntries();
    }

    /**
     * @param IndexTarget[] $targets
     *
     * @return array<string, int> class definition id => mapping checksum
     */
    private function collectClassChecksums(array $targets): array
    {
        $checksums = [];
        foreach ($targets as $target) {
            if (!$target->isClassIndex()) {
                continue;
            }
            $checksum = $this->settingsStoreService->getClassMappingCheckSum($target->getClassId());
            if ($checksum !== null) {
                $checksums[$target->getClassId()] = $checksum;
            }
        }

        return $checksums;
    }

    /**
     * Exports every target's documents to a temporary file and streams it into $storage,
     * unlinking the temp file right away either way.
     *
     * @param IndexTarget[] $targets
     *
     * @return ManifestIndex[]
     */
    private function exportAllIndices(
        SnapshotStorageInterface $storage,
        string $name,
        array $targets,
        ?callable $onIndexExported,
    ): array {
        $indices = [];
        foreach ($targets as $target) {
            $written = $this->exportIndex($target);

            try {
                $storage->writeFile($name, $target->getShortName() . '.ndjson.gz', $written->getPath());
            } finally {
                @unlink($written->getPath());
            }
            $indices[] = $this->manifestIndex(
                $target,
                $written->getDocumentCount(),
                $written->getBytes(),
                $written->getSha256(),
            );
            if ($onIndexExported !== null) {
                $onIndexExported($target, $written->getDocumentCount());
            }
        }

        return $indices;
    }

    /**
     * An index the source installation does not have is exported as an empty index rather than
     * left out: the importer only recreates what the manifest lists, so leaving it out would
     * keep a stale local index (with its old documents) in place while the import reports
     * success. Empty in the snapshot means empty after the import, like on the source.
     */
    private function exportIndex(IndexTarget $target): WrittenFile
    {
        $writer = DocumentFileWriter::createTemporary();

        try {
            if (!$this->searchIndexService->existsAlias($target->getAliasName())) {
                return $writer->finish();
            }
            $sizer = new PageSizer($this->pageSize, $this->pageBytes);
            $search = new Search(source: true);
            $search->setSortList(new FieldSortList([new FieldSort(SystemField::ID->getPath())]));
            $searchAfter = null;
            do {
                $pageSize = $sizer->nextPageSize();
                $search->setSize($pageSize);
                $search->setSearchAfter($searchAfter);
                // `false` is not usable here: with track_total_hits disabled the search engine
                // omits "hits.total" entirely, but SearchResultDenormalizer::denormalize()
                // unconditionally reads $searchResult['hits']['total']['value'] and throws.
                // An integer bound (rather than `true`) is enough: search_after pagination only
                // needs "hits.total" to exist, never its exact value, so `1` avoids the engine
                // counting every match on each page.
                $result = $this->searchIndexService->search($search, $target->getAliasName(), 1);
                $this->assertCompleteResponse($result->getResponse(), $target);
                $hits = $result->getHits();
                $bytesBefore = $writer->getRawBytes();
                foreach ($hits as $hit) {
                    $writer->write($hit->getSource());
                }
                $sizer->recordPage(count($hits), $writer->getRawBytes() - $bytesBefore);
                $this->logger?->debug('Snapshot export page', [
                    'index' => $target->getShortName(),
                    'requested' => $pageSize,
                    'received' => count($hits),
                    'documents_so_far' => $writer->getDocumentCount(),
                    'raw_bytes_so_far' => $writer->getRawBytes(),
                ]);
                $lastHit = $result->getLastHit();
                $searchAfter = $lastHit?->getSort();
            } while ($lastHit !== null && $searchAfter !== null && count($hits) === $pageSize);

            return $writer->finish();
        } catch (Throwable $e) {
            $writer->abort();

            throw $e;
        }
    }

    /**
     * A page can come back HTTP 200 and still be partial: `timed_out: true`, or failed shards
     * with only the surviving shards' hits. Writing those hits as if they were the page would
     * yield a snapshot that is incomplete and yet imports with matching counts, so the export
     * aborts instead; re-running it on a healthy cluster is the only correct recovery.
     *
     * @throws SnapshotExportException
     */
    private function assertCompleteResponse(array $response, IndexTarget $target): void
    {
        if (($response['timed_out'] ?? false) === true) {
            throw new SnapshotExportException(sprintf(
                'Export of index "%s" aborted: the search engine returned a partial (timed out) page',
                $target->getShortName(),
            ));
        }
        $failedShards = (int) ($response['_shards']['failed'] ?? 0);
        if ($failedShards > 0) {
            throw new SnapshotExportException(sprintf(
                'Export of index "%s" aborted: %d shard(s) failed, the page is partial',
                $target->getShortName(),
                $failedShards,
            ));
        }
    }

    private function manifestIndex(IndexTarget $target, int $documentCount, int $bytes, string $sha256): ManifestIndex
    {
        $version = $this->searchIndexService->getCurrentIndexVersion($target->getAliasName());

        return new ManifestIndex(
            shortName: $target->getShortName(),
            elementType: $target->getElementType(),
            classId: $target->getClassId(),
            sourceIndex: $target->getAliasName() . ($version !== '' ? '-' . $version : ''),
            documentCount: $documentCount,
            file: $target->getShortName() . '.ndjson.gz',
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
