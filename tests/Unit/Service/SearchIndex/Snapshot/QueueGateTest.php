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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Snapshot;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotExportException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Stats\IndexStats;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexStatsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\QueueGate;

final class QueueGateTest extends Unit
{
    public function testReturnsCountWithoutThreshold(): void
    {
        $gate = new QueueGate($this->statsService([42]), static fn (int $s) => null);

        $this->assertSame(42, $gate->await(null, 0));
    }

    public function testWaitsUntilBelowThreshold(): void
    {
        $slept = [];
        $gate = new QueueGate($this->statsService([500, 200, 50]), static function (int $s) use (&$slept): void {
            $slept[] = $s;
        }, 5);

        $this->assertSame(50, $gate->await(100, 60));
        $this->assertSame([5, 5], $slept);
    }

    public function testThrowsWhenStillAboveThresholdAfterWait(): void
    {
        $gate = new QueueGate($this->statsService([500, 500, 500, 500]), static fn (int $s) => null, 5);

        $this->expectException(SnapshotExportException::class);
        $this->expectExceptionMessage('500 entries');
        $gate->await(100, 10); // 10 s budget, 5 s polls: two sleeps, then give up
    }

    public function testZeroWaitRefusesImmediately(): void
    {
        $gate = new QueueGate($this->statsService([1]), static fn (int $s) => null);

        $this->expectException(SnapshotExportException::class);
        $gate->await(0, 0);
    }

    private function statsService(array $counts): IndexStatsServiceInterface
    {
        return $this->makeEmpty(IndexStatsServiceInterface::class, [
            'getStats' => static function () use (&$counts): IndexStats {
                $count = count($counts) > 1 ? array_shift($counts) : $counts[0];

                return new IndexStats($count, []);
            },
        ]);
    }
}
