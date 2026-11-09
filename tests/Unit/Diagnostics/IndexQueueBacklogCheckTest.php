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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Diagnostics;

use Codeception\Test\Unit;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\DriverManager;
use Pimcore\Bundle\GenericDataIndexBundle\Diagnostics\IndexQueueBacklogCheck;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Status\IndexQueueStatusService;
use Pimcore\Contracts\Diagnostics\CheckInterface;
use Pimcore\Contracts\Diagnostics\CheckResult;
use Pimcore\Contracts\Diagnostics\DataClass;
use Pimcore\Contracts\Diagnostics\DataContract;
use Psr\Clock\ClockInterface;
use ReflectionClass;

/**
 * @internal
 */
final class IndexQueueBacklogCheckTest extends Unit
{
    private const int NOW = 1_800_000_000;

    private Connection $connection;

    public function _before(): void
    {
        if (!interface_exists(CheckInterface::class)) {
            $this->markTestSkipped('The diagnostics contract ships with Pimcore 2026.4.');
        }

        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE ' . IndexQueue::TABLE . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                elementId INTEGER NOT NULL,
                elementType VARCHAR(20) NOT NULL,
                elementIndexName VARCHAR(255) NOT NULL,
                operation VARCHAR(20) NOT NULL,
                operationTime BIGINT NOT NULL,
                dispatched BIGINT NOT NULL DEFAULT 0
            )'
        );
    }

    public function testIdIsPrefixedWithTheBundleName(): void
    {
        $this->assertSame('generic_data_index.index_queue_backlog', $this->createCheck()->getId());
    }

    public function testEmptyQueueReportsZeroCountsAndNoFinding(): void
    {
        $result = $this->createCheck()->run();

        $this->assertSame([
            'queue_total' => 0,
            'queue_dispatched' => 0,
            'queue_pending' => 0,
            'oldest_pending_age_seconds' => null,
            'client_type' => 'openSearch',
            'client_name' => 'default',
            'index_prefix' => 'pimcore_',
        ], $result->metrics);
        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->notAvailable);
        $this->assertNoInstanceSecrets($result);
    }

    public function testOperationTimeInMillisecondsIsTurnedIntoAnAgeInSeconds(): void
    {
        $this->insertEntry(1, (self::NOW - 120) * 1000 + 999, 0);
        $this->insertEntry(2, (self::NOW - 30) * 1000, 0);
        $this->insertEntry(3, (self::NOW - 9000) * 1000, 1_700_000_000_000_123);

        $result = $this->createCheck()->run();

        $this->assertSame(3, $result->metrics['queue_total']);
        $this->assertSame(1, $result->metrics['queue_dispatched']);
        $this->assertSame(2, $result->metrics['queue_pending']);
        $this->assertSame(120, $result->metrics['oldest_pending_age_seconds']);
        $this->assertSame([], $result->findings);
    }

    public function testAnEntryInTheFutureCountsAsAgeZero(): void
    {
        $this->insertEntry(1, (self::NOW + 60) * 1000, 0);

        $this->assertSame(0, $this->createCheck()->run()->metrics['oldest_pending_age_seconds']);
    }

    public function testExactlyOneHourIsNoFinding(): void
    {
        $this->insertEntry(1, (self::NOW - 3600) * 1000, 0);

        $this->assertSame([], $this->createCheck()->run()->findings);
    }

    public function testOlderThanOneHourIsAWarning(): void
    {
        $this->insertEntry(1, (self::NOW - 3601) * 1000, 0);

        $result = $this->createCheck()->run();

        $this->assertSame([[
            'code' => 'index_queue_backlog',
            'severity' => 'warning',
            'message' => 'Index queue entries older than one hour are waiting.',
            'subject' => 'generic_data_index_queue',
        ]], $result->toArray()['findings']);
    }

    public function testExactlyOneDayIsStillAWarning(): void
    {
        $this->insertEntry(1, (self::NOW - 86400) * 1000, 0);

        $this->assertSame('warning', $this->createCheck()->run()->toArray()['findings'][0]['severity']);
    }

    public function testOlderThanOneDayIsCritical(): void
    {
        $this->insertEntry(1, (self::NOW - 86401) * 1000, 0);

        $result = $this->createCheck()->run();

        $this->assertSame([[
            'code' => 'index_queue_backlog',
            'severity' => 'critical',
            'message' => 'Index queue entries older than one day are waiting.',
            'subject' => 'generic_data_index_queue',
        ]], $result->toArray()['findings']);
    }

    public function testOldDispatchedEntriesAreNoBacklog(): void
    {
        $this->insertEntry(1, (self::NOW - 90000) * 1000, 1_700_000_000_000_123);

        $this->assertSame([], $this->createCheck()->run()->findings);
    }

    public function testMissingTableIsReportedAsNotAvailable(): void
    {
        $this->connection->executeStatement('DROP TABLE ' . IndexQueue::TABLE);

        $result = $this->createCheck()->run();

        $this->assertSame(
            [['subject' => 'generic_data_index_queue', 'reason' => 'table_missing']],
            $result->toArray()['not_available']
        );
        $this->assertSame([], $result->findings);
        $this->assertArrayNotHasKey('queue_total', $result->metrics);
        $this->assertNoInstanceSecrets($result);
    }

    public function testUnreadableQueueIsReportedWithoutMetricsAndWithoutTheExceptionMessage(): void
    {
        $failingConnection = $this->makeEmpty(Connection::class, [
            'createSchemaManager' => static function (): never {
                throw new ConnectionException(
                    "SQLSTATE[HY000] [1045] Access denied for user 'pimcore'@'db.internal' (using password: YES)"
                );
            },
        ]);

        $result = $this->createCheck(connection: $failingConnection)->run();

        $this->assertSame(
            [['subject' => 'generic_data_index_queue', 'reason' => 'not_readable']],
            $result->toArray()['not_available']
        );
        $this->assertSame([], $result->metrics);
        $this->assertSame([], $result->findings);
        $this->assertNoInstanceSecrets($result);
    }

    public function testConfigurationValuesThatAreNoIdentifiersAreNotReported(): void
    {
        $result = $this->createCheck(
            clientName: 'https://user:secret@search.internal:9200',
            indexPrefix: 'pimcore_'
        )->run();

        $this->assertNull($result->metrics['client_name']);
        $this->assertSame('pimcore_', $result->metrics['index_prefix']);
        $this->assertNoInstanceSecrets($result);
    }

    public function testDataContractIsDeclared(): void
    {
        $attributes = (new ReflectionClass(IndexQueueBacklogCheck::class))->getAttributes(DataContract::class);
        $this->assertCount(1, $attributes);

        $contract = $attributes[0]->newInstance();
        $this->assertSame([DataClass::Metric, DataClass::Configuration, DataClass::Identifier], $contract->returns);
        $this->assertSame(
            ['element ids', 'queue payload', 'search hosts and credentials', 'exception messages'],
            $contract->neverReturns
        );
        $this->assertSame(
            [
                'generic_data_index_queue (aggregates)',
                'search index configuration (client type, client name, prefix)',
            ],
            $contract->sources
        );
        $this->assertSame('doc/06_Diagnostics/README.md#index_queue_backlog', $contract->doc);
    }

    private function createCheck(
        ?Connection $connection = null,
        string $clientName = 'default',
        string $indexPrefix = 'pimcore_'
    ): IndexQueueBacklogCheck {
        $configService = $this->makeEmpty(SearchIndexConfigServiceInterface::class, [
            'getClientType' => 'openSearch',
            'getIndexPrefix' => $indexPrefix,
        ]);
        $clock = $this->makeEmpty(ClockInterface::class, [
            'now' => new DateTimeImmutable('@' . self::NOW),
        ]);

        return new IndexQueueBacklogCheck(
            statusService: new IndexQueueStatusService(
                connection: $connection ?? $this->connection,
                searchIndexConfigService: $configService,
                clientName: $clientName,
            ),
            clock: $clock,
        );
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

    private function assertNoInstanceSecrets(CheckResult $result): void
    {
        DiagnosticsAssertions::assertNoHostSchemeOrCredential($result);
    }
}
