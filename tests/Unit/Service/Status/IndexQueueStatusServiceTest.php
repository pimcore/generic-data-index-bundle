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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\Status;

use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexStatusException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Status\IndexQueueStatusService;

/**
 * @internal
 */
final class IndexQueueStatusServiceTest extends Unit
{
    private Connection $connection;

    public function _before(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
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

    public function testEmptyQueue(): void
    {
        $status = $this->createService()->getQueueStatus();

        $this->assertSame(0, $status->getTotal());
        $this->assertSame(0, $status->getPending());
        $this->assertSame(0, $status->getDispatched());
        $this->assertNull($status->getOldestPendingOperationTime());
        $this->assertNull($status->getOldestDispatchTime());
    }

    public function testQueueCountsPendingAndDispatchedEntries(): void
    {
        $this->insertEntry(1, 'asset', 1_700_000_000_300, 0);
        $this->insertEntry(2, 'asset', 1_700_000_000_100, 0);
        $this->insertEntry(3, 'dataObject', 1_700_000_000_050, 1_700_000_000_400_000_123);
        $this->insertEntry(4, 'document', 1_700_000_000_200, 0);

        $status = $this->createService()->getQueueStatus();

        $this->assertSame(4, $status->getTotal());
        $this->assertSame(3, $status->getPending());
        $this->assertSame(1, $status->getDispatched());
        // The dispatched entry is older, but only entries waiting for dispatch count.
        $this->assertSame(1_700_000_000_100, $status->getOldestPendingOperationTime());
        // The dispatch time comes from the dispatch id, not from the time the entry was queued.
        $this->assertSame(1_700_000_000_400, $status->getOldestDispatchTime());
    }

    public function testQueueWithOnlyDispatchedEntriesHasNoOldestPendingTime(): void
    {
        $this->insertEntry(1, 'asset', 1_700_000_000_100, 1_700_000_000_900_000_042);
        $this->insertEntry(2, 'asset', 1_700_000_000_200, 1_700_000_000_500_999_999);

        $status = $this->createService()->getQueueStatus();

        $this->assertSame(2, $status->getTotal());
        $this->assertSame(0, $status->getPending());
        $this->assertSame(2, $status->getDispatched());
        $this->assertNull($status->getOldestPendingOperationTime());
        $this->assertSame(1_700_000_000_500, $status->getOldestDispatchTime());
    }

    public function testDispatchIdWithoutUniquenessSuffixIsReadAsPlainMillisecondTimestamp(): void
    {
        $this->insertEntry(1, 'asset', 1_700_000_000_100, 1_700_000_000_300);

        $this->assertSame(1_700_000_000_300, $this->createService()->getQueueStatus()->getOldestDispatchTime());
    }

    public function testQueueStatusFailsWithDomainExceptionWhenTheTableCannotBeRead(): void
    {
        $this->connection->executeStatement('DROP TABLE ' . IndexQueue::TABLE);

        $this->expectException(IndexStatusException::class);

        $this->createService()->getQueueStatus();
    }

    public function testQueueTableIsAvailableWhenItExists(): void
    {
        $this->assertTrue($this->createService()->isQueueTableAvailable());
    }

    public function testQueueTableIsNotAvailableAfterItWasDropped(): void
    {
        $this->connection->executeStatement('DROP TABLE ' . IndexQueue::TABLE);

        $this->assertFalse($this->createService()->isQueueTableAvailable());
    }

    public function testClientConfigurationComesFromTheBundleConfiguration(): void
    {
        $configuration = $this->createService(
            clientType: 'elasticsearch',
            indexPrefix: 'shop_',
            clientName: 'search_cluster'
        )->getClientConfiguration();

        $this->assertSame('elasticsearch', $configuration->getClientType());
        $this->assertSame('search_cluster', $configuration->getClientName());
        $this->assertSame('shop_', $configuration->getIndexPrefix());
    }

    public function testClientConfigurationDoesNotTouchTheDatabase(): void
    {
        $this->connection->executeStatement('DROP TABLE ' . IndexQueue::TABLE);

        $configuration = $this->createService()->getClientConfiguration();

        $this->assertSame('openSearch', $configuration->getClientType());
    }

    private function createService(
        string $clientType = 'openSearch',
        string $indexPrefix = 'pimcore_',
        string $clientName = 'default'
    ): IndexQueueStatusService {
        $configService = $this->makeEmpty(SearchIndexConfigServiceInterface::class, [
            'getClientType' => $clientType,
            'getIndexPrefix' => $indexPrefix,
        ]);

        return new IndexQueueStatusService(
            connection: $this->connection,
            searchIndexConfigService: $configService,
            clientName: $clientName,
        );
    }

    private function insertEntry(int $elementId, string $elementType, int $operationTime, int $dispatched): void
    {
        $this->connection->insert(IndexQueue::TABLE, [
            'elementId' => $elementId,
            'elementType' => $elementType,
            'elementIndexName' => $elementType,
            'operation' => 'update',
            'operationTime' => $operationTime,
            'dispatched' => $dispatched,
        ]);
    }
}
