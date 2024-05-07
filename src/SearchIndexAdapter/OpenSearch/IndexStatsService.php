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
        protected readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        protected readonly IndexQueueRepository $indexQueueRepository,
        protected readonly SearchIndexServiceInterface $openSearchService,
    ) {
    }

    public function getStats(): IndexStats
    {

        $allStats = $this->openSearchService->getStats(
            $this->searchIndexConfigService->getIndexPrefix() . '*'
        );

        $indices = [];
        foreach ($allStats['indices'] as $indexName => $index) {
            $indices[] = new IndexStatsIndex(
                indexName: $indexName,
                itemsCount: $index['total']['docs']['count'],
                sizeInKb: round(((int)$index['total']['store']['size_in_bytes'] / 1024), 2)
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
