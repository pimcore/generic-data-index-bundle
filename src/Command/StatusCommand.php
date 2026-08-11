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

use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DefaultSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexStatsServiceInterface;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Read-only diagnostic overview of the Generic Data Index: queue depth and the live indices
 * with their document counts, plus a warning for any index that has both blue/green versions
 * present at once (the fingerprint of an interrupted reindex).
 *
 * @internal
 */
final class StatusCommand extends AbstractCommand
{
    public function __construct(
        private readonly IndexStatsServiceInterface $indexStatsService,
        private readonly IndexQueueRepository $indexQueueRepository,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('generic-data-index:status')
            ->setDescription(
                'Read-only overview of the search index: queue depth, indices and their document '
                . 'counts, and interrupted-reindex leftovers.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stats = $this->indexStatsService->getStats();

        $io->section('Index queue');
        $io->definitionList(
            ['entries' => (string) $stats->getCountIndexQueueEntries()],
            ['pending dispatch' => $this->indexQueueRepository->dispatchableItemExists() ? 'yes' : 'no'],
        );

        $indices = $stats->getIndices();
        $io->section(sprintf('Indices (%d)', count($indices)));

        if ($indices === []) {
            $io->warning('No indices found. Has the index been built?');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($indices as $index) {
            $rows[] = [$index->getIndexName(), $index->getItemsCount(), $index->getSizeInKb()];
        }
        $io->table(['Index', 'Documents', 'Size (KB)'], $rows);

        $leftovers = $this->detectBlueGreenLeftovers(array_map(
            static fn ($index) => $index->getIndexName(),
            $indices
        ));

        if ($leftovers !== []) {
            $io->warning(sprintf(
                "The following indices have both '-%s' and '-%s' versions present at once, which "
                . "usually indicates an interrupted reindex:\n  %s",
                DefaultSearchService::INDEX_VERSION_EVEN,
                DefaultSearchService::INDEX_VERSION_ODD,
                implode("\n  ", $leftovers)
            ));
        }

        return self::SUCCESS;
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
