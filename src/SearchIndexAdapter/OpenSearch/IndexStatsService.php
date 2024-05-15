<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch;

use Exception;
use OpenSearch\Client;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Stats\IndexStats;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Stats\IndexStatsIndex;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexStatsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;

final class IndexStatsService implements IndexStatsServiceInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        private readonly IndexQueueRepository $indexQueueRepository,
        private readonly SearchIndexServiceInterface $openSearchService,
        private readonly Client $openSearchClient,
    ) {
    }

    public function getStats(): IndexStats
    {

        $allStats = $this->openSearchService->getStats(
            $this->searchIndexConfigService->getIndexPrefix() . '*'
        );

        $aggregationResult = $this->openSearchClient->search([
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

        $indices = [];
        foreach ($aggregationResult['aggregations']['indices']['buckets'] as $bucket) {
            $sizeInBytes = (int)($allStats['indices'][$bucket['key']]['total']['store']['size_in_bytes'] ?? 0);
            $indices[] = new IndexStatsIndex(
                indexName: $bucket['key'],
                itemsCount: $bucket['doc_count'],
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
        } catch(Exception $e) {
            $this->logger->error('Error while counting index queue entries: '. $e->getMessage());
        }

        return 0;
    }
}
