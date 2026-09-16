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

        $targets = $this->resolveExistingTargets();
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

        if ($options->dryRun) {
            $indices = [];
            foreach ($targets as $target) {
                $indices[] = $this->manifestIndex(
                    $target,
                    $this->searchIndexService->getCount(new Search(), $target->aliasName),
                    0,
                    '',
                );
            }

            return new ExportResult($name, $manifest->withIndices($indices), true);
        }

        try {
            $indices = $this->exportAllIndices($storage, $name, $targets, $onIndexExported);

            $manifest = new Manifest(
                createdAt: $manifest->createdAt,
                genericDataIndexVersion: $manifest->genericDataIndexVersion,
                pimcoreVersion: $manifest->pimcoreVersion,
                clientType: $manifest->clientType,
                indexPrefix: $manifest->indexPrefix,
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
            $manifest->durationSeconds,
        ));

        return new ExportResult($name, $manifest, false);
    }

    private function queueCount(): int
    {
        return $this->indexStatsService->getStats()->getCountIndexQueueEntries();
    }

    /** @return IndexTarget[] */
    private function resolveExistingTargets(): array
    {
        return array_values(array_filter(
            $this->indexResolver->resolveAll(),
            fn (IndexTarget $target) => $this->searchIndexService->existsAlias($target->aliasName),
        ));
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
                $storage->writeFile($name, $target->shortName . '.ndjson.gz', $written->path);
            } finally {
                @unlink($written->path);
            }
            $indices[] = $this->manifestIndex($target, $written->documentCount, $written->bytes, $written->sha256);
            if ($onIndexExported !== null) {
                $onIndexExported($target, $written->documentCount);
            }
        }

        return $indices;
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
                // An integer bound (rather than `true`) is enough: search_after pagination only
                // needs "hits.total" to exist, never its exact value, so `1` avoids the engine
                // counting every match on each page.
                $result = $this->searchIndexService->search($search, $target->aliasName, 1);
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
