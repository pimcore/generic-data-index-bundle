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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Stats\IndexStats;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Stats\IndexStatsIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexStatsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\SearchClient\SearchClientInterface;

/**
 * @internal
 */
final class IndexStatsService implements IndexStatsServiceInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        private readonly IndexQueueRepository $indexQueueRepository,
        private readonly SearchIndexServiceInterface $defaultSearchService
    ) {
    }

    public function getStats(): IndexStats
    {

        $allStats = $this->defaultSearchService->getStats(
            $this->searchIndexConfigService->getIndexPrefix() . '*'
        );

        $aggregationResult = $this->client->search([
            'index' => $this->searchIndexConfigService->getIndexPrefix() . '*',
            'body' => [
                'size' => 0,
                'aggs' => [
                    'indices' => [
                        'terms' => [
                            'field' => '_index',
                            'size' => 10000,
                            'order' => [
                                '_key' => 'asc',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Document counts come from the terms aggregation on _index, which counts top-level
        // (searchable) documents - the element count we want. Index stats are deliberately NOT used
        // for the count: their docs.count is a raw Lucene total that would be inflated by nested
        // block/table sub-documents and by replica shards. Every index that holds documents appears
        // in the aggregation (its 10000-bucket cap is far above any realistic number of GDI indices),
        // so an index absent from it is empty and correctly counts as 0.
        $docCountByIndex = [];
        foreach ($aggregationResult['aggregations']['indices']['buckets'] as $bucket) {
            $docCountByIndex[$bucket['key']] = $bucket['doc_count'];
        }

        // Enumerate every index from _stats - it lists empty indices too, which the aggregation
        // omits - so an empty live index is still reported (with a 0 count) rather than dropped.
        $allIndexStats = $allStats['indices'] ?? [];
        ksort($allIndexStats);

        $indices = [];
        foreach ($allIndexStats as $indexName => $indexStats) {
            $sizeInBytes = (int)($indexStats['total']['store']['size_in_bytes'] ?? 0);
            $indices[] = new IndexStatsIndex(
                indexName: $indexName,
                itemsCount: $docCountByIndex[$indexName] ?? 0,
                sizeInKb: round(($sizeInBytes / 1024), 2)
            );
        }

        return new IndexStats(
            countIndexQueueEntries: $this->countIndexQueueEntries(),
            indices: $indices,
        );
    }

    private function countIndexQueueEntries(): int
    {
        try {
            return $this->indexQueueRepository->countIndexQueueEntries();
        } catch (Exception $e) {
            $this->logger->error('Error while counting index queue entries: '. $e->getMessage());
        }

        return 0;
    }
}
