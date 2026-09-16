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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\SearchIndex;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Stats\IndexStats;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexStatsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;

final class IndexStatsServiceNoIndexTest extends Unit
{
    protected IndexTester $tester;

    protected function _after(): void
    {
        // The test deleted every GDI index. Recreate them so the rest of the (shuffled) suite
        // sees the pre-test state: the update command enqueues every element again, so clearing
        // the queue afterwards is what actually restores "nothing pending", not the recreation
        // of the indices/mappings themselves.
        $this->tester->runConsoleCommand('generic-data-index:update:index');
        $this->tester->clearQueue();
    }

    public function testGetStatsDoesNotFailWhenNoIndexExists(): void
    {
        $configService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        $prefix = $configService->getIndexPrefix();

        if ($prefix === '') {
            // Deleting "*" would wipe every index on the cluster, not just this installation's
            // GDI indices - refuse to run in that configuration instead of risking collateral damage.
            $this->markTestSkipped('Index prefix is empty in this test environment; refusing to delete "*".');
        }

        // OpenSearch/Elasticsearch with `action.destructive_requires_name` enabled (as CI does)
        // rejects a wildcard delete, so enumerate the matching indices and delete them by name.
        $searchClient = $this->tester->getIndexSearchClient();
        $allIndices = $searchClient->getAllIndices(['index' => $prefix . '*']);
        foreach ($allIndices as $key => $indexInfo) {
            $indexName = is_array($indexInfo) ? ($indexInfo['index'] ?? $key) : $indexInfo;
            $searchClient->deleteIndex(['index' => $indexName]);
        }

        $stats = $this->tester->grabService(IndexStatsServiceInterface::class)->getStats();

        $this->assertInstanceOf(IndexStats::class, $stats);
        $this->assertSame([], $stats->getIndices());
        $this->assertIsInt($stats->getCountIndexQueueEntries());
    }
}
