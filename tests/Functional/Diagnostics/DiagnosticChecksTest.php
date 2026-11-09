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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Diagnostics;

use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\Support\DiagnosticChecks;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Diagnostics\DiagnosticsAssertions;
use Pimcore\Contracts\Diagnostics\CheckInterface;
use Pimcore\Db;

/**
 * Runs the diagnostic checks in the booted kernel against the real queue table and search cluster. Every write
 * happens inside a transaction that is rolled back.
 *
 * @internal
 */
final class DiagnosticChecksTest extends Unit
{
    private const string BACKLOG_CHECK = 'generic_data_index.index_queue_backlog';

    private const string HEALTH_CHECK = 'generic_data_index.index_health';

    protected IndexTester $tester;

    private Connection $connection;

    protected function _before(): void
    {
        if (!interface_exists(CheckInterface::class)) {
            $this->markTestSkipped('The diagnostics contract ships with Pimcore 2026.4.');
        }

        $this->connection = Db::get();
        $this->connection->beginTransaction();
        $this->connection->executeStatement('DELETE FROM ' . IndexQueue::TABLE);
    }

    protected function _after(): void
    {
        if (isset($this->connection) && $this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
    }

    public function testChecksAreRegisteredInTheKernel(): void
    {
        $ids = $this->getChecks()->getIds();

        $this->assertContains(self::BACKLOG_CHECK, $ids);
        $this->assertContains(self::HEALTH_CHECK, $ids);
    }

    public function testBacklogOfAnEmptyQueue(): void
    {
        $result = $this->getChecks()->get(self::BACKLOG_CHECK)->run();

        $this->assertSame(
            [
                'queue_total',
                'queue_dispatched',
                'queue_pending',
                'oldest_pending_age_seconds',
                'oldest_dispatched_age_seconds',
                'client_type',
                'client_name',
                'index_prefix',
            ],
            array_keys($result->metrics)
        );
        $this->assertSame(0, $result->metrics['queue_total']);
        $this->assertNull($result->metrics['oldest_pending_age_seconds']);
        $this->assertNull($result->metrics['oldest_dispatched_age_seconds']);
        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->notAvailable);
        DiagnosticsAssertions::assertNoHostSchemeOrCredential($result);
    }

    public function testBacklogOfASeededQueue(): void
    {
        $now = time();
        $this->insertEntry(1, ($now - 7200) * 1000, 0);
        $this->insertEntry(2, ($now - 10) * 1000, 0);
        $this->insertEntry(3, ($now - 60) * 1000, $now * 1000 * 1_000_000 + 1);

        $result = $this->getChecks()->get(self::BACKLOG_CHECK)->run();

        $this->assertSame(3, $result->metrics['queue_total']);
        $this->assertSame(2, $result->metrics['queue_pending']);
        $this->assertSame(1, $result->metrics['queue_dispatched']);
        $this->assertGreaterThanOrEqual(7200, $result->metrics['oldest_pending_age_seconds']);
        $this->assertSame([[
            'code' => 'index_queue_backlog',
            'severity' => 'warning',
            'message' => 'Index queue entries older than one hour are waiting.',
            'subject' => 'generic_data_index_queue',
        ]], $result->toArray()['findings']);
        DiagnosticsAssertions::assertNoHostSchemeOrCredential($result);

        $this->insertEntry(4, ($now - 90000) * 1000, 0);

        $this->assertSame([[
            'code' => 'index_queue_backlog',
            'severity' => 'critical',
            'message' => 'Index queue entries older than one day are waiting.',
            'subject' => 'generic_data_index_queue',
        ]], $this->getChecks()->get(self::BACKLOG_CHECK)->run()->toArray()['findings']);
    }

    public function testDispatchedEntriesThatNoWorkerProcessesAreReported(): void
    {
        $now = time();
        // Queued and dispatched more than one day ago, encoded as IndexQueueRepository writes dispatch ids.
        $this->insertEntry(1, ($now - 100000) * 1000, ($now - 90000) * 1000 * 1_000_000 + 123);

        $result = $this->getChecks()->get(self::BACKLOG_CHECK)->run();

        $this->assertSame(0, $result->metrics['queue_pending']);
        $this->assertSame(1, $result->metrics['queue_dispatched']);
        $this->assertGreaterThanOrEqual(90000, $result->metrics['oldest_dispatched_age_seconds']);
        $this->assertSame([[
            'code' => 'index_queue_not_consumed',
            'severity' => 'critical',
            'message' => 'Dispatched index queue entries older than one day are not processed.',
            'subject' => 'generic_data_index_queue',
        ]], $result->toArray()['findings']);
        DiagnosticsAssertions::assertNoHostSchemeOrCredential($result);
    }

    public function testHealthOfTheTestIndices(): void
    {
        $this->tester->runConsoleCommand('generic-data-index:update:index');

        $result = $this->getChecks()->get(self::HEALTH_CHECK)->run();

        $this->assertSame(
            ['index_count', 'indices_red', 'indices_yellow', 'documents_total', 'size_bytes_total'],
            array_keys($result->metrics)
        );
        $this->assertGreaterThan(0, $result->metrics['index_count']);
        $this->assertSame(0, $result->metrics['indices_red']);
        $this->assertSame([], $result->notAvailable);
        foreach ($result->toArray()['findings'] as $finding) {
            $this->assertSame('index_not_green', $finding['code']);
            $this->assertSame('The index is not green.', $finding['message']);
        }
        DiagnosticsAssertions::assertNoHostSchemeOrCredential($result);
    }

    private function getChecks(): DiagnosticChecks
    {
        return $this->tester->grabService(DiagnosticChecks::class);
    }

    private function insertEntry(int $elementId, int $operationTime, int $dispatched): void
    {
        $this->connection->insert(IndexQueue::TABLE, [
            'elementId' => $elementId,
            'elementType' => 'asset',
            'elementIndexName' => 'asset',
            'operation' => 'update',
            'operationTime' => $operationTime,
            'dispatched' => $dispatched,
        ]);
    }
}
