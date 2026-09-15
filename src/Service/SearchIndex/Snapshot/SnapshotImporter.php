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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Snapshot\ClassCompatibilityStatus;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\BulkOperationException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\InvalidSnapshotException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotIncompatibleException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportedIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\Manifest;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\BulkOperationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\AssetIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DocumentIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\IndexHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;

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
        private readonly BulkOperationServiceInterface $bulkOperationService,
        private readonly SettingsStoreServiceInterface $settingsStoreService,
        private readonly DataObjectIndexHandler $dataObjectIndexHandler,
        private readonly AssetIndexHandler $assetIndexHandler,
        private readonly DocumentIndexHandler $documentIndexHandler,
        private readonly DocumentFileReader $documentFileReader,
        private readonly int $bulkSize,
    ) {
    }

    public function import(SnapshotStorageInterface $storage, string $name, ImportOptions $options, ?callable $onIndexImported = null): ImportResult
    {
        $manifest = $storage->readManifest($name);
        $notices = [];
        if ($manifest->clientType !== $this->searchIndexConfigService->getClientType()) {
            $notices[] = sprintf('Snapshot was taken on %s, this installation runs %s. Mappings are regenerated locally, documents are portable.', $manifest->clientType, $this->searchIndexConfigService->getClientType());
        }
        if ($manifest->indexPrefix !== $this->searchIndexConfigService->getIndexPrefix()) {
            $notices[] = sprintf('Snapshot index prefix "%s" differs from local "%s"; local names are used.', $manifest->indexPrefix, $this->searchIndexConfigService->getIndexPrefix());
        }

        $report = $this->compatibilityChecker->check($manifest);
        if (!$report->isCompatible() && !$options->force) {
            throw new SnapshotIncompatibleException(sprintf(
                'Snapshot "%s" does not match the local class definitions for class ids [%s]. Import the matching database dump or pass --force to skip these classes.',
                $name, implode(', ', $report->incompatibleClassIds())
            ), $report);
        }

        $entries = $this->selectEntries($manifest, $options->only);
        $skipped = [];
        $plan = [];
        foreach ($entries as $entry) {
            $target = $this->indexResolver->resolveManifestIndex($entry);
            if ($target === null) {
                $skipped[$entry->shortName] = 'no local counterpart (class definition missing or unknown element type)';

                continue;
            }
            if ($target->isClassIndex() && $report->statusOf((string) $target->getClassId()) === ClassCompatibilityStatus::INCOMPATIBLE) {
                $skipped[$entry->shortName] = 'class mapping incompatible, skipped because of --force';

                continue;
            }
            if ($target->isClassIndex() && $report->statusOf((string) $target->getClassId()) === ClassCompatibilityStatus::UNVERIFIED) {
                $skipped[$entry->shortName] = 'no class mapping checksum in the manifest, skipped because of --force';

                continue;
            }
            $plan[] = [$entry, $target];
        }

        if ($options->dryRun) {
            $imported = array_map(static fn (array $pair) => new ImportedIndex($pair[1]->shortName, $pair[1]->aliasName, $pair[0]->documentCount, 0), $plan);

            return new ImportResult($name, $manifest, $report, $imported, $skipped, $notices, true);
        }

        $imported = [];
        foreach ($plan as [$entry, $target]) {
            $index = $this->replay($storage, $name, $entry, $target);
            $imported[] = $index;
            if ($onIndexImported !== null) {
                $onIndexImported($index);
            }
        }

        return new ImportResult($name, $manifest, $report, $imported, $skipped, $notices, false);
    }

    /** @return ManifestIndex[] */
    private function selectEntries(Manifest $manifest, array $only): array
    {
        if ($only === []) {
            return $manifest->indices;
        }
        $unknown = array_diff($only, array_map(static fn (ManifestIndex $i) => $i->shortName, $manifest->indices));
        if ($unknown !== []) {
            throw new SnapshotImportException(sprintf('Snapshot has no indices named [%s]', implode(', ', $unknown)));
        }

        return array_values(array_filter($manifest->indices, static fn (ManifestIndex $i) => in_array($i->shortName, $only, true)));
    }

    /**
     * Provisions (recreates) the local index for the target. Returns the freshly computed class
     * mapping checksum for a class index, or null otherwise; the caller is responsible for
     * stamping it into the settings store, and only once the replay has actually succeeded (see
     * {@see replay()}). Stamping it here, before the documents are replayed, would mark the
     * mapping as current even if the subsequent bulk import fails, leaving an empty or partial
     * index that {@see \Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassDefinition\ClassDefinitionReindexService}
     * would then never self-heal.
     */
    private function provision(IndexTarget $target): ?int
    {
        $handler = $this->handlerFor($target);
        $context = $target->classDefinition;
        if ($this->searchIndexService->existsAlias($target->aliasName)) {
            $handler->deleteIndex($context);
        }
        $mappingProperties = $handler->getMappingProperties($context);
        $handler->updateMapping(context: $context, forceCreateIndex: true, mappingProperties: $mappingProperties);

        return $target->isClassIndex() ? $handler->getClassMappingCheckSum($mappingProperties) : null;
    }

    private function replay(SnapshotStorageInterface $storage, string $name, ManifestIndex $entry, IndexTarget $target): ImportedIndex
    {
        if (preg_match('/^[A-Za-z0-9._-]+$/D', $entry->file) !== 1 || in_array($entry->file, ['.', '..'], true)) {
            throw new SnapshotImportException(sprintf('Invalid file name "%s" in manifest', $entry->file));
        }

        $local = $this->documentFileReader->temporaryPath();
        $classMappingChecksum = null;

        try {
            // Download and verify BEFORE touching the live index: a truncated or corrupted
            // file must be rejected while the previous, still-good index is untouched.
            $storage->readFileToLocal($name, $entry->file, $local);
            $this->documentFileReader->verifyHash($local, $entry->sha256);

            $classMappingChecksum = $this->provision($target);

            $pending = 0;
            foreach ($this->documentFileReader->read($local) as $document) {
                $id = $document[FieldCategory::SYSTEM_FIELDS->value][SystemField::ID->value] ?? null;
                if (!is_int($id)) {
                    throw new SnapshotImportException(sprintf('Document without integer system_fields.id in "%s"', $entry->file));
                }
                $this->bulkOperationService->add($target->aliasName, $id, $document);
                if (++$pending >= $this->bulkSize) {
                    $this->bulkOperationService->commit();
                    $pending = 0;
                }
            }
            $this->bulkOperationService->commit();
        } catch (InvalidSnapshotException|BulkOperationException|FilesystemException $e) {
            throw new SnapshotImportException(sprintf('Import of index "%s" failed: %s', $target->shortName, $e->getMessage()), 0, $e);
        } finally {
            @unlink($local);
        }
        $this->searchIndexService->refreshIndex($target->aliasName);
        $actual = $this->searchIndexService->getCount(new Search(), $target->aliasName);
        $this->logger?->info(sprintf('Imported %d/%d documents into %s', $actual, $entry->documentCount, $target->aliasName));

        // Stamp the checksum only now: the bulk commit above succeeded and the index has been
        // counted, so the settings store is only ever updated once the mapping it describes is
        // actually backed by a fully-replayed index. An incomplete replay (actual count doesn't
        // match the manifest's expected count) must not stamp the checksum either: a partial
        // index would otherwise look "current" and the self-healing reindex would never fix it.
        if ($target->isClassIndex() && $classMappingChecksum !== null) {
            if ($actual === $entry->documentCount) {
                $this->settingsStoreService->storeClassMapping((string) $target->getClassId(), $classMappingChecksum);
            } else {
                $this->logger?->warning(sprintf(
                    'Not stamping class mapping checksum for %s: replay imported %d/%d documents',
                    $target->aliasName, $actual, $entry->documentCount
                ));
            }
        }

        return new ImportedIndex($target->shortName, $target->aliasName, $entry->documentCount, $actual);
    }

    private function handlerFor(IndexTarget $target): IndexHandlerInterface
    {
        return match ($target->elementType) {
            ElementType::ASSET->value => $this->assetIndexHandler,
            ElementType::DOCUMENT->value => $this->documentIndexHandler,
            ElementType::DATA_OBJECT->value => $this->dataObjectIndexHandler,
            default => throw new SnapshotImportException(sprintf('Unknown element type "%s"', $target->elementType)),
        };
    }
}
