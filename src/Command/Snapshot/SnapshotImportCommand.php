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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotLock;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorageInterface;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Throwable;

/**
 * Imports a snapshot bundle written by generic-data-index:snapshot:export: recreates the
 * indices with the local mappings and replays the documents. Never enqueues elements.
 *
 * @internal
 */
final class SnapshotImportCommand extends AbstractCommand
{
    public function __construct(
        private readonly SnapshotStorageInterface $snapshotStorage,
        private readonly SnapshotImporterInterface $snapshotImporter,
        private readonly IndexStatsServiceInterface $indexStatsService,
        private readonly LockFactory $lockFactory,
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
        $lock = SnapshotLock::create($this->lockFactory);
        if (!$lock->acquire()) {
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
            ) use ($lock): void {
                $lock->refresh();
                $this->io->writeln(
                    sprintf(
                        '  %s: %d/%d documents',
                        $index->getShortName(),
                        $index->getActualCount(),
                        $index->getExpectedCount(),
                    ),
                );
            });

            $this->renderResult($result);
            if (!$result->isSuccessful()) {
                $this->io->error('Document counts do not match the manifest for at least one index.');

                return self::FAILURE;
            }
            $this->io->success($result->isDryRun() ? 'Nothing written.' : 'Snapshot imported.');

            return self::SUCCESS;
        } catch (SnapshotIncompatibleException $e) {
            $this->renderIncompatible($e);

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->io->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
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
        foreach ($result->getNotices() as $notice) {
            $this->io->note($notice);
        }
        $this->io->section(
            $result->isDryRun()
                ? sprintf('Dry run of snapshot "%s"', $result->getName())
                : sprintf('Imported snapshot "%s"', $result->getName()),
        );
        if ($result->isDryRun()) {
            $this->renderPlannedTable($result);
        } else {
            $this->renderImportedTable($result);
        }
        foreach ($result->getSkipped() as $shortName => $reason) {
            $this->io->writeln(sprintf('skipped %s: %s', $shortName, $reason));
        }
        if ($result->getReport()->getMissingInManifest() !== []) {
            $this->io->note(
                'Local classes without data in the snapshot: '
                . implode(', ', $result->getReport()->getMissingInManifest()),
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
                $i->getShortName(),
                $i->getAliasName(),
                $i->getExpectedCount(),
            ], $result->getImported()),
        );
    }

    private function renderImportedTable(ImportResult $result): void
    {
        $this->io->table(
            ['index', 'alias', 'expected', 'actual', 'ok'],
            array_map(static fn (ImportedIndex $i) => [
                $i->getShortName(),
                $i->getAliasName(),
                $i->getExpectedCount(),
                $i->getActualCount(),
                $i->isComplete() ? 'yes' : 'NO',
            ], $result->getImported()),
        );
    }

    private function renderIncompatible(SnapshotIncompatibleException $e): void
    {
        $this->io->error($e->getMessage());
        $this->io->table(
            ['class', 'id', 'manifest checksum', 'stored', 'computed from local definition'],
            array_map(
                static fn (ClassCompatibility $c) => [
                    $c->getClassName() ?? '',
                    $c->getClassId(),
                    $c->getManifestChecksum() ?? '',
                    $c->getStoredChecksum() ?? '',
                    $c->getComputedChecksum() ?? '',
                ],
                array_filter(
                    $e->getReport()->getClasses(),
                    static fn (ClassCompatibility $c) => $c->getStatus() === ClassCompatibilityStatus::INCOMPATIBLE
                        || $c->getStatus() === ClassCompatibilityStatus::UNVERIFIED,
                ),
            ),
        );
        $this->io->writeln(
            'Import the database dump that belongs to this snapshot, or pass --force to skip these classes.',
        );
    }
}
