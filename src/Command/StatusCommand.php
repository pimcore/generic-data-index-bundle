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

namespace Pimcore\Bundle\GenericDataIndexBundle\Command;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexStatusException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexStatus;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexStatusReport;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexCountRepositoryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DefaultSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexStatsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexStatusServiceInterface;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
final class StatusCommand extends AbstractCommand
{
    private const OPTION_INDEX = 'index';

    private const OPTION_JSON = 'json';

    private const OPTION_WAIT = 'wait';

    private const OPTION_TIMEOUT = 'timeout';

    private const OPTION_CHECK = 'check';

    private const DEFAULT_TIMEOUT_SECONDS = 3600;

    private const POLL_INTERVAL_SECONDS = 5;

    public function __construct(
        private readonly IndexStatsServiceInterface $indexStatsService,
        private readonly IndexQueueRepository $indexQueueRepository,
        private readonly IndexStatusServiceInterface $indexStatusService,
        private readonly IndexCountRepositoryInterface $countRepository,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('generic-data-index:status')
            ->setDescription(
                'Read-only overview of the search index: queue depth, indices with their document counts, and '
                . 'the status of every index (complete, rebuilding with progress, copying, degraded, incomplete).'
            )
            ->addOption(
                self::OPTION_INDEX,
                null,
                InputOption::VALUE_REQUIRED,
                'Show only this index: its alias name, a class name, "asset", "document" or "data-object-folder"'
            )
            ->addOption(self::OPTION_JSON, null, InputOption::VALUE_NONE, 'Print the index status as JSON')
            ->addOption(
                self::OPTION_WAIT,
                null,
                InputOption::VALUE_NONE,
                'Wait until the index (or every index) is complete; exits with 1 when the timeout is reached'
            )
            ->addOption(
                self::OPTION_TIMEOUT,
                null,
                InputOption::VALUE_REQUIRED,
                'Seconds to wait with --wait',
                (string) self::DEFAULT_TIMEOUT_SECONDS
            )
            ->addOption(
                self::OPTION_CHECK,
                null,
                InputOption::VALUE_NONE,
                'Exit with 1 when the index (or any index) is not complete'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $indexName = $input->getOption(self::OPTION_INDEX);
        $indexName = is_string($indexName) && $indexName !== '' ? $indexName : null;

        try {
            $report = $input->getOption(self::OPTION_WAIT)
                ? $this->waitForCompletion($indexName, (int) $input->getOption(self::OPTION_TIMEOUT), $io)
                : $this->readReport($indexName);
        } catch (IndexStatusException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        if ($input->getOption(self::OPTION_JSON)) {
            $output->writeln(json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } elseif ($indexName === null) {
            $this->renderOverview($io, $report);
        } else {
            $this->renderStatusTable($io, $report);
        }

        if ($input->getOption(self::OPTION_CHECK) && !$report->isComplete()) {
            $io->error(sprintf('Not complete: %s', $this->describeIncomplete($report)));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @throws Exception
     */
    private function readReport(?string $indexName): IndexStatusReport
    {
        if ($indexName === null) {
            return $this->indexStatusService->getAll();
        }

        return new IndexStatusReport(
            [$this->indexStatusService->getStatusByIndexName($indexName)],
            $this->countRepository->countQueueEntries(),
            null
        );
    }

    /**
     * @throws Exception
     */
    private function waitForCompletion(?string $indexName, int $timeoutSeconds, SymfonyStyle $io): IndexStatusReport
    {
        $deadline = microtime(true) + $timeoutSeconds;
        while (true) {
            $report = $this->readReport($indexName);
            if ($report->isComplete()) {
                return $report;
            }
            if (microtime(true) >= $deadline) {
                throw new IndexStatusException(sprintf(
                    'Still not complete after %d seconds: %s',
                    $timeoutSeconds,
                    $this->describeIncomplete($report)
                ));
            }
            foreach ($report->getIncompleteIndices() as $status) {
                $io->writeln(sprintf(
                    '  waiting for %s: %s%s',
                    $status->getLabel(),
                    $status->getState()->value,
                    $status->getPercentage() === null ? '' : sprintf(' %.1f%%', $status->getPercentage())
                ), OutputInterface::VERBOSITY_VERBOSE);
            }
            sleep(self::POLL_INTERVAL_SECONDS);
        }
    }

    /**
     * @throws Exception
     */
    private function renderOverview(SymfonyStyle $io, IndexStatusReport $report): void
    {
        $stats = $this->indexStatsService->getStats();

        $io->section('Index queue');
        $queue = [
            ['entries' => (string) $stats->getCountIndexQueueEntries()],
            ['pending dispatch' => $this->indexQueueRepository->dispatchableItemExists() ? 'yes' : 'no'],
        ];
        if ($report->getFailedMessages() !== null) {
            $queue[] = ['failed messages' => (string) $report->getFailedMessages()];
        }
        $io->definitionList(...$queue);

        $indices = $stats->getIndices();
        $io->section(sprintf('Indices (%d)', count($indices)));

        if ($indices === []) {
            $io->warning('No indices found. Has the index been built?');

            return;
        }

        $rows = [];
        foreach ($indices as $index) {
            $rows[] = [$index->getIndexName(), $index->getItemsCount(), $index->getSizeInKb()];
        }
        $io->table(['Index', 'Documents', 'Size (KB)'], $rows);

        $this->renderStatusTable($io, $report);

        $copying = [];
        foreach ($report->getIndices() as $status) {
            if ($status->isCopyRunning()) {
                $copying[$status->getIndexName()] = true;
            }
        }
        $leftovers = array_diff_key(
            array_flip($this->detectBlueGreenLeftovers(array_map(
                static fn ($index) => $index->getIndexName(),
                $indices
            ))),
            $copying
        );

        if ($leftovers !== []) {
            $io->warning(sprintf(
                "The following indices have both '-%s' and '-%s' versions present at once without a running "
                . "copy, which usually indicates an interrupted reindex:\n  %s",
                DefaultSearchService::INDEX_VERSION_EVEN,
                DefaultSearchService::INDEX_VERSION_ODD,
                implode("\n  ", array_keys($leftovers))
            ));
        }
    }

    private function renderStatusTable(SymfonyStyle $io, IndexStatusReport $report): void
    {
        $io->section('Index status');
        $rows = [];
        foreach ($report->getIndices() as $status) {
            $rows[] = [
                $status->getLabel(),
                $status->getIndexName(),
                $status->getState()->value,
                $status->getPercentage() === null ? '-' : sprintf('%.1f %%', $status->getPercentage()),
                $status->getDocumentCount(),
                $status->getElementCount(),
                $status->getPendingEntries(),
                $status->isCopyRunning() ? 'yes' : 'no',
                $this->yesNoUnknown($status->isMappingCurrent()),
            ];
        }
        $io->table(
            ['Index', 'Alias', 'State', 'Progress', 'Documents', 'Elements', 'Waiting', 'Copy', 'Mapping current'],
            $rows
        );

        if ($report->isComplete()) {
            $io->success('All indices are complete.');
        }
    }

    private function describeIncomplete(IndexStatusReport $report): string
    {
        return implode(', ', array_map(
            static fn (IndexStatus $status): string => $status->getLabel() . ' (' . $status->getState()->value . ')',
            $report->getIncompleteIndices()
        ));
    }

    private function yesNoUnknown(?bool $value): string
    {
        return match ($value) {
            true => 'yes',
            false => 'no',
            null => 'unknown',
        };
    }

    /**
     * Base index names that appear with BOTH the -even and -odd suffix in the index list.
     *
     * @param string[] $indexNames
     *
     * @return string[]
     */
    private function detectBlueGreenLeftovers(array $indexNames): array
    {
        $seenEven = [];
        $seenOdd = [];
        foreach ($indexNames as $name) {
            if (str_ends_with($name, '-' . DefaultSearchService::INDEX_VERSION_EVEN)) {
                $seenEven[substr($name, 0, -strlen('-' . DefaultSearchService::INDEX_VERSION_EVEN))] = true;
            } elseif (str_ends_with($name, '-' . DefaultSearchService::INDEX_VERSION_ODD)) {
                $seenOdd[substr($name, 0, -strlen('-' . DefaultSearchService::INDEX_VERSION_ODD))] = true;
            }
        }

        // array_keys() already returns a 0-indexed list, so no array_values() wrapper is needed
        // (newer PHPStan flags the redundant call).
        return array_keys(array_intersect_key($seenEven, $seenOdd));
    }
}
