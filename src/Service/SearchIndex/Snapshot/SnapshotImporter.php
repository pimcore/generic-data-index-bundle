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

use League\Flysystem\FilesystemException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Snapshot\ClassCompatibilityStatus;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotIncompatibleException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\CompatibilityReport;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportedIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\Manifest;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Throwable;

/**
 * @internal
 */
final class SnapshotImporter implements SnapshotImporterInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly SnapshotIndexResolverInterface $indexResolver,
        private readonly CompatibilityCheckerInterface $compatibilityChecker,
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        private readonly SearchIndexServiceInterface $searchIndexService,
        private readonly IndexProvisionerInterface $indexProvisioner,
        private readonly DocumentFileReader $documentFileReader,
        private readonly DocumentReplayerInterface $documentReplayer,
    ) {
    }

    public function import(
        SnapshotStorageInterface $storage,
        string $name,
        ImportOptions $options,
        ?callable $onIndexImported = null,
    ): ImportResult {
        $manifest = $storage->readManifest($name);
        $notices = $this->collectNotices($manifest);
        $report = $this->compatibilityChecker->check($manifest);
        $this->assertCompatible($name, $report, $options);

        $skipped = [];
        $plan = $this->plan($manifest->indices, $report, $skipped);

        if ($options->dryRun) {
            return new ImportResult($name, $manifest, $report, $this->plannedIndices($plan), $skipped, $notices, true);
        }

        // Two phases: every planned file is downloaded and verified BEFORE any index is
        // provisioned, so a corrupt or missing file anywhere in the plan is caught before any
        // live index has been touched, not just before the index it belongs to. Skipped entries
        // are not part of the plan on purpose: their files are never imported, and downloading
        // a multi-gigabyte class file only to hash it would cost the operator time for nothing.
        $preflighted = $this->preflight($storage, $name, $plan);
        $imported = $this->apply($preflighted, $onIndexImported);

        return new ImportResult($name, $manifest, $report, $imported, $skipped, $notices, false);
    }

    /**
     * @param array<int, array{0: ManifestIndex, 1: IndexTarget}> $plan
     *
     * @return ImportedIndex[]
     */
    private function plannedIndices(array $plan): array
    {
        return array_map(
            static fn (array $pair) => new ImportedIndex(
                $pair[1]->shortName,
                $pair[1]->aliasName,
                $pair[0]->documentCount,
                0,
            ),
            $plan,
        );
    }

    /**
     * @return string[]
     */
    private function collectNotices(Manifest $manifest): array
    {
        $notices = [];
        if ($manifest->clientType !== $this->searchIndexConfigService->getClientType()) {
            $notices[] = sprintf(
                'Snapshot was taken on %s, this installation runs %s. '
                . 'Mappings are regenerated locally, documents are portable.',
                $manifest->clientType,
                $this->searchIndexConfigService->getClientType(),
            );
        }
        if ($manifest->indexPrefix !== $this->searchIndexConfigService->getIndexPrefix()) {
            $notices[] = sprintf(
                'Snapshot index prefix "%s" differs from local "%s"; local names are used.',
                $manifest->indexPrefix,
                $this->searchIndexConfigService->getIndexPrefix(),
            );
        }

        return $notices;
    }

    /**
     * Throws when the compatibility gate blocks the whole import: the report contains at least
     * one incompatible or unverified class. The exception always carries the full report, so
     * callers can see everything that is incompatible.
     */
    private function assertCompatible(string $name, CompatibilityReport $report, ImportOptions $options): void
    {
        if ($options->force || $report->isCompatible()) {
            return;
        }

        throw new SnapshotIncompatibleException(sprintf(
            'Snapshot "%s" does not match the local class definitions for class ids [%s]. '
            . 'Import the matching database dump or pass --force to skip these classes.',
            $name,
            implode(', ', $report->incompatibleClassIds()),
        ), $report);
    }

    /**
     * Resolves the manifest's entries to local index targets and decides, per entry, whether it
     * can be imported or must be skipped (no local counterpart, or an incompatible/unverified
     * class mapping). $skipped is populated with the skip reason for every entry left out of the
     * returned plan.
     *
     * @param ManifestIndex[] $entries
     * @param array<string, string> $skipped short index name => reason, populated by reference
     *
     * @return array<int, array{0: ManifestIndex, 1: IndexTarget}>
     */
    private function plan(array $entries, CompatibilityReport $report, array &$skipped): array
    {
        $plan = [];
        foreach ($entries as $entry) {
            $target = $this->indexResolver->resolveManifestIndex($entry);
            if ($target === null) {
                $skipped[$entry->shortName] = 'no local counterpart '
                    . '(class definition missing or unknown element type)';

                continue;
            }
            $classStatus = $target->isClassIndex() ? $report->statusOf((string) $target->getClassId()) : null;
            if ($classStatus === ClassCompatibilityStatus::INCOMPATIBLE) {
                $skipped[$entry->shortName] = 'class mapping incompatible, skipped because of --force';

                continue;
            }
            if ($classStatus === ClassCompatibilityStatus::UNVERIFIED) {
                $skipped[$entry->shortName] = 'no class mapping checksum in the manifest, skipped because of --force';

                continue;
            }
            $plan[] = [$entry, $target];
        }

        return $plan;
    }

    /**
     * Downloads and verifies every planned entry's file before any index is provisioned. On any
     * failure, every temp file already downloaded by this call is unlinked and the failure is
     * surfaced as a SnapshotImportException (Flysystem/gzip/JSON causes wrapped with the failing
     * index's name, same as {@see apply()} does for a replay failure).
     *
     * @param array<int, array{0: ManifestIndex, 1: IndexTarget}> $plan
     *
     * @return array<int, array{0: ManifestIndex, 1: IndexTarget, 2: string}> entry, target, verified local path
     */
    private function preflight(SnapshotStorageInterface $storage, string $name, array $plan): array
    {
        $preflighted = [];

        try {
            foreach ($plan as [$entry, $target]) {
                $preflighted[] = [$entry, $target, $this->downloadAndVerify($storage, $name, $entry, $target)];
            }
        } catch (Throwable $e) {
            foreach ($preflighted as [, , $local]) {
                @unlink($local);
            }

            throw $e;
        }

        return $preflighted;
    }

    private function downloadAndVerify(
        SnapshotStorageInterface $storage,
        string $name,
        ManifestIndex $entry,
        IndexTarget $target,
    ): string {
        // Deliberately ASCII-only: manifest file names are always generated by the exporter
        // from ASCII short index names (see SnapshotExporter::exportIndex()), never from
        // user input, so a Unicode-aware character class would only weaken this validation.
        if (preg_match('/^[A-Za-z0-9._-]+$/D', $entry->file) !== 1 || in_array($entry->file, ['.', '..'], true)) {
            throw new SnapshotImportException(sprintf('Invalid file name "%s" in manifest', $entry->file));
        }

        // Defense in depth: Manifest::fromArray() already enforces this when a manifest is
        // parsed from a snapshot, but a Manifest can also be built directly in code (e.g. by
        // Manifest::withIndices()), so the importer must not trust the invariant blindly.
        $expectedFile = $entry->shortName . '.ndjson.gz';
        if ($entry->file !== $expectedFile) {
            throw new SnapshotImportException(sprintf(
                'Manifest index entry "%s" references file "%s", expected "%s"',
                $entry->shortName,
                $entry->file,
                $expectedFile,
            ));
        }

        $local = $this->documentFileReader->temporaryPath();

        try {
            // Download and verify BEFORE touching any live index: a truncated or corrupted
            // file anywhere in the plan must be rejected before any index has been provisioned.
            $storage->readFileToLocal($name, $entry->file, $local);
            $this->documentFileReader->verifyHash($local, $entry->sha256);
        } catch (InvalidSnapshotException|FilesystemException|SnapshotImportException $e) {
            @unlink($local);

            throw new SnapshotImportException(
                sprintf('Import of index "%s" failed: %s', $target->shortName, $e->getMessage()),
                0,
                $e,
            );
        } catch (Throwable $e) {
            @unlink($local);

            throw $e;
        }

        return $local;
    }

    /**
     * Provisions and replays every preflighted index. Not atomic across indices: if replaying
     * one index fails, every index replayed before it is complete; the failing index itself has
     * already been recreated and is left partially filled; indices not yet reached are untouched.
     * Every preflighted temp file is unlinked exactly once, whether its index is reached or not.
     *
     * @param array<int, array{0: ManifestIndex, 1: IndexTarget, 2: string}> $preflighted
     *
     * @return ImportedIndex[]
     */
    private function apply(array $preflighted, ?callable $onIndexImported): array
    {
        $imported = [];

        try {
            foreach ($preflighted as [$entry, $target, $local]) {
                try {
                    $index = $this->replay($entry, $target, $local);
                } finally {
                    @unlink($local);
                }
                $imported[] = $index;
                if ($onIndexImported !== null) {
                    $onIndexImported($index);
                }
            }
        } finally {
            // A failure partway through must still clean up every not-yet-reached preflighted
            // file; already-replayed ones were already unlinked above (unlinking twice is a
            // harmless no-op).
            foreach ($preflighted as [, , $local]) {
                @unlink($local);
            }
        }

        return $imported;
    }

    private function replay(ManifestIndex $entry, IndexTarget $target, string $local): ImportedIndex
    {
        // The checksum is computed here, before the documents are replayed, but only ever
        // stamped into the settings store below, once the replay has actually succeeded.
        // Stamping it now, before the bulk import, would mark the mapping as current even if
        // the import subsequently fails, leaving an empty or partial index that
        // {@see ClassDefinitionReindexService} would then never self-heal.
        $this->indexProvisioner->provision($target);
        $classMappingChecksum = $this->indexProvisioner->computeClassMappingChecksum($target);

        $this->documentReplayer->replay($target, $entry, $local);
        $this->searchIndexService->refreshIndex($target->aliasName);
        $actual = $this->searchIndexService->getCount(new Search(), $target->aliasName);
        $this->logger?->info(sprintf(
            'Imported %d/%d documents into %s',
            $actual,
            $entry->documentCount,
            $target->aliasName,
        ));

        $this->assertReplayComplete($target, $actual, $entry->documentCount);
        $this->stampClassMapping($target, $classMappingChecksum);

        return new ImportedIndex($target->shortName, $target->aliasName, $entry->documentCount, $actual);
    }

    /**
     * An incomplete replay (actual count doesn't match the manifest's expected count) must abort
     * the import the same way any other replay failure does: a partial index must never be
     * stamped as current, and indices later in the plan must stay untouched rather than be
     * provisioned on top of a replay already known to be wrong.
     */
    private function assertReplayComplete(IndexTarget $target, int $actual, int $expected): void
    {
        if ($actual === $expected) {
            return;
        }

        throw new SnapshotImportException(sprintf(
            'Replay of index "%s" imported %d/%d documents, aborting import of the remaining indices',
            $target->aliasName,
            $actual,
            $expected,
        ));
    }

    /**
     * Stamp the checksum only once the bulk commit above succeeded, the index has been counted,
     * and {@see assertReplayComplete()} has confirmed the replay is complete, so the settings
     * store is only ever updated once the mapping it describes is actually backed by a
     * fully-replayed index.
     */
    private function stampClassMapping(IndexTarget $target, ?int $classMappingChecksum): void
    {
        if ($target->isClassIndex() && $classMappingChecksum !== null) {
            $this->indexProvisioner->stampClassMapping($target, $classMappingChecksum);
        }
    }
}
