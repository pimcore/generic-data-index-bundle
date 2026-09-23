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

use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ExportOptions;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\IndexTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Snapshot\ManifestIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotExporterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotLock;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorageInterface;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Throwable;

/**
 * Exports every search index into a portable snapshot bundle (gzipped JSON lines + manifest)
 * in the configured Flysystem storage. Read-only against the search engine.
 *
 * @internal
 */
final class SnapshotExportCommand extends AbstractCommand
{
    public function __construct(
        private readonly SnapshotStorageInterface $snapshotStorage,
        private readonly SnapshotExporterInterface $snapshotExporter,
        private readonly LockFactory $lockFactory,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('generic-data-index:snapshot:export')
            ->setDescription(
                'Export all search indices into a portable snapshot bundle for import on another installation.',
            )
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Snapshot name (directory). Default: UTC timestamp.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Resolve indices and counts, write nothing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = SnapshotLock::create($this->lockFactory);
        if (!$lock->acquire()) {
            $this->io->error('Another snapshot export or import is already running.');

            return self::FAILURE;
        }

        try {
            $name = (string) ($input->getOption('name') ?? gmdate('Y-m-d\TH-i-s\Z'));
            $options = new ExportOptions(dryRun: (bool) $input->getOption('dry-run'));
            $result = $this->snapshotExporter->export(
                $this->snapshotStorage,
                $name,
                $options,
                function (IndexTarget $target, int $count) use ($lock): void {
                    $lock->refresh();
                    $this->io->writeln(sprintf('  %s: %d documents', $target->getShortName(), $count));
                },
            );
            $manifest = $result->getManifest();
            $this->io->section(
                $result->isDryRun() ? sprintf('Dry run of snapshot "%s"', $name) : sprintf('Snapshot "%s"', $name),
            );
            $this->io->table(
                ['index', 'element type', 'class', 'documents', 'bytes'],
                array_map(
                    static fn (ManifestIndex $i) => [
                        $i->getShortName(),
                        $i->getElementType(),
                        $i->getClassId() ?? '',
                        $i->getDocumentCount(),
                        $i->getBytes(),
                    ],
                    $manifest->getIndices(),
                ),
            );
            $this->io->writeln(sprintf(
                'queue entries before: %d, after: %d, duration: %d s',
                $manifest->getQueueEntriesBefore(),
                $manifest->getQueueEntriesAfter(),
                $manifest->getDurationSeconds(),
            ));
            if ($manifest->getQueueEntriesBefore() > 0) {
                $this->io->note(
                    'The index queue is not idle: the snapshot may lag the database. '
                    . 'Stop messenger consumers before exporting for a clean baseline.',
                );
            }
            $this->io->success($result->isDryRun() ? 'Nothing written.' : 'Snapshot written.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->io->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
