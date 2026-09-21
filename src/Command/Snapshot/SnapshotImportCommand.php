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

namespace Pimcore\Bundle\GenericDataIndexBundle\Command\Snapshot;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Snapshot\ClassCompatibilityStatus;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotIncompatibleException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ClassCompatibility;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportedIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ImportResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexStatsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotImporterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorageInterface;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Imports a snapshot bundle written by generic-data-index:snapshot:export: recreates the
 * indices with the local mappings and replays the documents. Never enqueues elements.
 *
 * @internal
 */
final class SnapshotImportCommand extends AbstractCommand
{
    use LockableTrait;

    public function __construct(
        private readonly SnapshotStorageInterface $snapshotStorage,
        private readonly SnapshotImporterInterface $snapshotImporter,
        private readonly IndexStatsServiceInterface $indexStatsService,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('generic-data-index:snapshot:import')
            ->setDescription(
                'Import a snapshot bundle into the local search indices without reindexing from the database.',
            )
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Snapshot name. Default: newest complete snapshot in the configured storage.',
            )
            ->addOption(
                'from-path',
                null,
                InputOption::VALUE_REQUIRED,
                'Read the snapshot from this local directory instead of the configured storage.',
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Continue when class mappings do not match; mismatched classes are skipped.',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Run the compatibility check and print the plan, write nothing.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Shared name with SnapshotExportCommand: export and import must be mutually
        // exclusive, since a concurrent export could read a half-written snapshot or race
        // the class-mapping checksum stamping the export/import cycle depends on.
        if (!$this->lock('generic-data-index:snapshot')) {
            $this->io->error('Another snapshot export or import is already running.');

            return self::FAILURE;
        }

        try {
            $storage = $this->resolveStorage($input);
            if ($storage === null) {
                return self::FAILURE;
            }
            $name = $input->getArgument('name') ?? $storage->latestSnapshotName();
            if ($name === null) {
                $this->io->error(
                    'No snapshot found in the storage. Run generic-data-index:snapshot:export first or pass a name.',
                );

                return self::FAILURE;
            }
            $options = new ImportOptions(
                force: (bool) $input->getOption('force'),
                dryRun: (bool) $input->getOption('dry-run'),
            );

            $queued = $this->indexStatsService->getStats()->getCountIndexQueueEntries();
            if ($queued > 0) {
                $this->io->warning(sprintf(
                    'The local index queue holds %d entries. '
                    . 'Stop messenger consumers during the import to avoid interleaved writes.',
                    $queued,
                ));
            }

            $result = $this->snapshotImporter->import($storage, (string) $name, $options, function (
                ImportedIndex $index,
            ): void {
                $this->io->writeln(
                    sprintf('  %s: %d/%d documents', $index->shortName, $index->actualCount, $index->expectedCount),
                );
            });

            $this->renderResult($result);
            if (!$result->isSuccessful()) {
                $this->io->error('Document counts do not match the manifest for at least one index.');

                return self::FAILURE;
            }
            $this->io->success($result->dryRun ? 'Nothing written.' : 'Snapshot imported.');

            return self::SUCCESS;
        } catch (SnapshotIncompatibleException $e) {
            $this->renderIncompatible($e);

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->io->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $this->release();
        }
    }

    private function resolveStorage(InputInterface $input): ?SnapshotStorageInterface
    {
        $fromPath = $input->getOption('from-path');
        if ($fromPath === null) {
            return $this->snapshotStorage;
        }
        if (!is_dir($fromPath)) {
            $this->io->error(sprintf('"%s" is not a directory.', $fromPath));

            return null;
        }

        return new SnapshotStorage(new Filesystem(new LocalFilesystemAdapter($fromPath)));
    }

    private function renderResult(ImportResult $result): void
    {
        foreach ($result->notices as $notice) {
            $this->io->note($notice);
        }
        $this->io->section(
            $result->dryRun
                ? sprintf('Dry run of snapshot "%s"', $result->name)
                : sprintf('Imported snapshot "%s"', $result->name),
        );
        if ($result->dryRun) {
            $this->renderPlannedTable($result);
        } else {
            $this->renderImportedTable($result);
        }
        foreach ($result->skipped as $shortName => $reason) {
            $this->io->writeln(sprintf('skipped %s: %s', $shortName, $reason));
        }
        if ($result->report->missingInManifest !== []) {
            $this->io->note(
                'Local classes without data in the snapshot: ' . implode(', ', $result->report->missingInManifest),
            );
        }
    }

    /**
     * A dry run never wrote anything, so its table must not read like a failed import: no
     * actual/ok columns at all, just what would be imported and how many documents are planned.
     */
    private function renderPlannedTable(ImportResult $result): void
    {
        $this->io->table(
            ['index', 'alias', 'documents (planned)'],
            array_map(static fn (ImportedIndex $i) => [
                $i->shortName,
                $i->aliasName,
                $i->expectedCount,
            ], $result->imported),
        );
    }

    private function renderImportedTable(ImportResult $result): void
    {
        $this->io->table(
            ['index', 'alias', 'expected', 'actual', 'ok'],
            array_map(static fn (ImportedIndex $i) => [
                $i->shortName,
                $i->aliasName,
                $i->expectedCount,
                $i->actualCount,
                $i->isComplete() ? 'yes' : 'NO',
            ], $result->imported),
        );
    }

    private function renderIncompatible(SnapshotIncompatibleException $e): void
    {
        $this->io->error($e->getMessage());
        $this->io->table(
            ['class', 'id', 'manifest checksum', 'stored', 'computed from local definition'],
            array_map(
                static fn (ClassCompatibility $c) => [
                    $c->className ?? '',
                    $c->classId,
                    $c->manifestChecksum ?? '',
                    $c->storedChecksum ?? '',
                    $c->computedChecksum ?? '',
                ],
                array_filter(
                    $e->report->classes,
                    static fn (ClassCompatibility $c) => $c->status === ClassCompatibilityStatus::INCOMPATIBLE
                        || $c->status === ClassCompatibilityStatus::UNVERIFIED,
                ),
            ),
        );
        $this->io->writeln(
            'Import the database dump that belongs to this snapshot, or pass --force to skip these classes.',
        );
    }
}
