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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Status;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexStatusException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Status\ClientConfiguration;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Status\QueueStatus;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final readonly class IndexQueueStatusService implements IndexQueueStatusServiceInterface
{
    public function __construct(
        private Connection $connection,
        private SearchIndexConfigServiceInterface $searchIndexConfigService,
        private string $clientName,
    ) {
    }

    public function getQueueStatus(): QueueStatus
    {
        try {
            $dispatched = $this->connection->quoteIdentifier('dispatched');
            $row = $this->connection->fetchAssociative(
                'SELECT
                    SUM(CASE WHEN ' . $dispatched . ' = 0 THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN ' . $dispatched . ' = 0 THEN 0 ELSE 1 END) AS dispatched,
                    MIN(CASE WHEN ' . $dispatched . ' = 0 THEN '
                        . $this->connection->quoteIdentifier('operationTime') . ' END) AS oldestPending
                FROM ' . $this->connection->quoteIdentifier(IndexQueue::TABLE)
            );
        } catch (DBALException $e) {
            throw new IndexStatusException('The index queue could not be read.', 0, $e);
        }

        $oldestPending = $row['oldestPending'] ?? null;

        return new QueueStatus(
            pending: (int) ($row['pending'] ?? 0),
            dispatched: (int) ($row['dispatched'] ?? 0),
            oldestPendingOperationTime: $oldestPending === null ? null : (int) $oldestPending,
        );
    }

    public function getClientConfiguration(): ClientConfiguration
    {
        return new ClientConfiguration(
            clientType: $this->searchIndexConfigService->getClientType(),
            clientName: $this->clientName,
            indexPrefix: $this->searchIndexConfigService->getIndexPrefix(),
        );
    }
}
