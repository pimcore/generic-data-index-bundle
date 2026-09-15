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
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\SnapshotStorageInterface;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Exports every search index into a portable snapshot bundle (gzipped JSON lines + manifest)
 * in the configured Flysystem storage. Read-only against the search engine.
 *
 * @internal
 */
final class SnapshotExportCommand extends AbstractCommand
{
    use LockableTrait;

    public function __construct(
        private readonly SnapshotStorageInterface $snapshotStorage,
        private readonly SnapshotExporterInterface $snapshotExporter,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('generic-data-index:snapshot:export')
            ->setDescription('Export all search indices into a portable snapshot bundle for import on another installation.')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Snapshot name (directory). Default: UTC timestamp.')
            ->addOption('max-queue-entries', null, InputOption::VALUE_REQUIRED, 'Refuse to export while the index queue holds more entries than this.')
            ->addOption('wait', null, InputOption::VALUE_REQUIRED, 'With --max-queue-entries: seconds to wait for the queue to drain before giving up.', '0')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Resolve indices and counts, write nothing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $this->io->error('Another snapshot export is already running.');

            return self::FAILURE;
        }

        try {
            $name = (string) ($input->getOption('name') ?? gmdate('Y-m-d\TH-i-s\Z'));
            $maxQueue = $input->getOption('max-queue-entries');
            $options = new ExportOptions(
                maxQueueEntries: $maxQueue === null ? null : (int) $maxQueue,
                waitSeconds: (int) $input->getOption('wait'),
                dryRun: (bool) $input->getOption('dry-run'),
            );
            $result = $this->snapshotExporter->export($this->snapshotStorage, $name, $options, function (IndexTarget $target, int $count): void {
                $this->io->writeln(sprintf('  %s: %d documents', $target->shortName, $count));
            });
            $manifest = $result->manifest;
            $this->io->section($result->dryRun ? sprintf('Dry run of snapshot "%s"', $name) : sprintf('Snapshot "%s"', $name));
            $this->io->table(
                ['index', 'element type', 'class', 'documents', 'bytes'],
                array_map(static fn (ManifestIndex $i) => [$i->shortName, $i->elementType, $i->classId ?? '', $i->documentCount, $i->bytes], $manifest->indices)
            );
            $this->io->writeln(sprintf('queue entries before: %d, after: %d, duration: %d s', $manifest->queueEntriesBefore, $manifest->queueEntriesAfter, $manifest->durationSeconds));
            if ($result->deletedSnapshots !== []) {
                $this->io->writeln('rotated out: ' . implode(', ', $result->deletedSnapshots));
            }
            if ($result->rotationError !== null) {
                $this->io->warning('Snapshot written, but rotation failed: ' . $result->rotationError);
            }
            $this->io->success($result->dryRun ? 'Nothing written.' : 'Snapshot written.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->io->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $this->release();
        }
    }
}
