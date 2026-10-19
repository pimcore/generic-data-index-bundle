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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\SearchIndex\Status;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexStatusState;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexStatus;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexStatusReport;

/**
 * @internal
 */
final class IndexStatusTest extends Unit
{
    public function testRebuildProgressFollowsThePendingEntries(): void
    {
        $status = $this->makeStatus(IndexStatusState::REBUILDING, documents: 100, elements: 100, pending: 25);

        $this->assertSame(75.0, $status->getPercentage());
        $this->assertFalse($status->isComplete());
    }

    public function testDegradedProgressFollowsTheDocumentsWithoutChecksum(): void
    {
        $status = $this->makeStatus(IndexStatusState::DEGRADED, documents: 10, elements: 10, withoutChecksum: 3);

        $this->assertSame(70.0, $status->getPercentage());
    }

    public function testCompleteAndIncompleteHaveNoPercentage(): void
    {
        $this->assertNull($this->makeStatus(IndexStatusState::COMPLETE, 10, 10)->getPercentage());
        $this->assertNull($this->makeStatus(IndexStatusState::INCOMPLETE, 5, 10)->getPercentage());
        $this->assertTrue($this->makeStatus(IndexStatusState::COMPLETE, 10, 10)->isComplete());
    }

    public function testAnEmptyIndexRebuildsToHundredPercent(): void
    {
        $this->assertSame(100.0, $this->makeStatus(IndexStatusState::REBUILDING, 0, 0)->getPercentage());
        $this->assertSame(100.0, $this->makeStatus(IndexStatusState::DEGRADED, 0, 0)->getPercentage());
    }

    public function testPercentageStaysWithinBounds(): void
    {
        // more pending entries than elements (new objects queued during the rebuild)
        $this->assertSame(0.0, $this->makeStatus(IndexStatusState::REBUILDING, 100, 100, pending: 150)->getPercentage());
    }

    public function testNegativeCountsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeStatus(IndexStatusState::COMPLETE, -1, 0);
    }

    public function testAnEmptyIndexNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IndexStatus('', 'assets', IndexStatusState::COMPLETE, 0, 0, 0, 0, false, true, true);
    }

    public function testToArrayCarriesEveryFact(): void
    {
        $array = $this->makeStatus(IndexStatusState::REBUILDING, 100, 100, pending: 50, queuedAt: 1700000000000)->toArray();

        $this->assertSame('pimcore_asset', $array['index']);
        $this->assertSame('rebuilding', $array['state']);
        $this->assertFalse($array['complete']);
        $this->assertSame(50.0, $array['percentage']);
        $this->assertSame(1700000000000, $array['rebuildQueuedAt']);
        $this->assertNull($array['mappingCurrent']);
    }

    public function testReportListsTheIncompleteIndices(): void
    {
        $complete = $this->makeStatus(IndexStatusState::COMPLETE, 10, 10);
        $rebuilding = $this->makeStatus(IndexStatusState::REBUILDING, 10, 10, pending: 5);
        $report = new IndexStatusReport([$complete, $rebuilding], 5, 0);

        $this->assertFalse($report->isComplete());
        $this->assertSame([$rebuilding], $report->getIncompleteIndices());
        $this->assertTrue((new IndexStatusReport([$complete], 0, null))->isComplete());
        $this->assertSame(2, count((new IndexStatusReport([$complete, $rebuilding], 0, null))->toArray()['indices']));
    }

    private function makeStatus(
        IndexStatusState $state,
        int $documents,
        int $elements,
        int $pending = 0,
        int $withoutChecksum = 0,
        ?int $queuedAt = null
    ): IndexStatus {
        return new IndexStatus(
            indexName: 'pimcore_asset',
            label: 'assets',
            state: $state,
            documentCount: $documents,
            elementCount: $elements,
            pendingEntries: $pending,
            documentsWithoutChecksum: $withoutChecksum,
            copyRunning: false,
            mappingCurrent: null,
            settingsCurrent: null,
            rebuildQueuedAt: $queuedAt,
        );
    }
}
