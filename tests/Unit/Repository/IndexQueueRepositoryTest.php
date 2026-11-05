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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Repository;

use Codeception\Test\Unit;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @internal
 */
final class IndexQueueRepositoryTest extends Unit
{
    private const TIMESTAMP = 1_700_000_000_000;

    private Connection&MockObject $connection;

    private LoggerInterface&MockObject $logger;

    public function _before(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('quoteIdentifier')->willReturnArgument(0);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testEnqueueRetriesTheChunkTransactionAfterADeadlock(): void
    {
        $this->connection->method('iterateAssociative')->willReturn($this->assetRows());
        $this->connection->method('getTransactionNestingLevel')->willReturn(0);
        $this->connection->expects($this->exactly(2))->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->once())->method('commit');
        $this->logger->expects($this->once())->method('warning');

        $attempts = 0;
        $this->connection->method('executeStatement')->willReturnCallback(
            function () use (&$attempts): int {
                $attempts++;
                if ($attempts === 1) {
                    throw $this->deadlock();
                }

                return 1;
            }
        );

        $this->createRepository()->enqueueBySelectQuery($this->queryBuilder());

        $this->assertSame(2, $attempts);
    }

    public function testEnqueueRethrowsTheDeadlockWhenAllAttemptsFail(): void
    {
        $this->connection->method('iterateAssociative')->willReturn($this->assetRows());
        $this->connection->method('getTransactionNestingLevel')->willReturn(0);
        $this->connection->expects($this->exactly(3))->method('beginTransaction');
        $this->connection->expects($this->exactly(3))->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        $this->connection->method('executeStatement')->willThrowException($this->deadlock());

        $this->expectException(DeadlockException::class);

        $this->createRepository()->enqueueBySelectQuery($this->queryBuilder());
    }

    public function testEnqueueDoesNotRetryInsideAnOuterTransaction(): void
    {
        // A deadlock rolls back the whole outer transaction, so replaying only the chunk would be wrong.
        $this->connection->method('iterateAssociative')->willReturn($this->assetRows());
        $this->connection->method('getTransactionNestingLevel')->willReturn(1);
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->method('executeStatement')->willThrowException($this->deadlock());

        $this->expectException(DeadlockException::class);

        $this->createRepository()->enqueueBySelectQuery($this->queryBuilder());
    }

    public function testDispatchItemsClaimsTheSelectedRowsByPrimaryKey(): void
    {
        $threshold = (self::TIMESTAMP - 86_400_000) * 1_000_000;

        $this->connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->with(
                $this->stringContains('LIMIT 400'),
                ['threshold' => $threshold]
            )
            ->willReturn(['9', '4', '27']);

        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('id IN (:ids)'),
                    $this->stringContains('dispatched < :threshold'),
                    $this->logicalNot($this->stringContains('LIMIT'))
                ),
                $this->callback(
                    static fn (array $params): bool => $params['ids'] === [4, 9, 27]
                        && $params['threshold'] === $threshold
                        && intdiv($params['dispatchId'], 1_000_000) === self::TIMESTAMP
                ),
                ['ids' => ArrayParameterType::INTEGER]
            )
            ->willReturn(3);

        $dispatchId = $this->createRepository()->dispatchItems(400);

        $this->assertSame(self::TIMESTAMP, intdiv($dispatchId, 1_000_000));
    }

    public function testDispatchItemsClaimsLargeBatchesInBoundedStatementsWithinOneTransaction(): void
    {
        // Keeps every statement far below the 65,535 placeholders of native prepared statements.
        $this->connection->method('fetchFirstColumn')->willReturn(range(1, 12_000));
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');

        $claimedIds = [];
        $dispatchIds = [];
        $this->connection->expects($this->exactly(3))
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql, array $params) use (&$claimedIds, &$dispatchIds): int {
                    $claimedIds = array_merge($claimedIds, $params['ids']);
                    $dispatchIds[] = $params['dispatchId'];

                    return count($params['ids']);
                }
            );

        $this->createRepository()->dispatchItems(100_000);

        $this->assertSame(range(1, 12_000), $claimedIds);
        $this->assertCount(1, array_unique($dispatchIds));
    }

    public function testDispatchItemsRollsBackAPartialClaim(): void
    {
        $this->connection->method('fetchFirstColumn')->willReturn(range(1, 12_000));
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $statements = 0;
        $this->connection->method('executeStatement')->willReturnCallback(
            function () use (&$statements): int {
                if (++$statements === 2) {
                    throw $this->deadlock();
                }

                return 5_000;
            }
        );

        $this->expectException(DeadlockException::class);

        $this->createRepository()->dispatchItems(100_000);
    }

    public function testDispatchItemsRefillsABatchShortenedByAConcurrentDispatcher(): void
    {
        // A short batch ends QueueMessageService's drain loop, so rows taken by another dispatcher are
        // replaced by a fresh pick instead of returning fewer rows while more are dispatchable.
        $limits = [];
        $this->connection->expects($this->exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnCallback(static function (string $sql) use (&$limits): array {
                preg_match('/LIMIT (\d+)/', $sql, $match);
                $limits[] = (int)$match[1];

                return count($limits) === 1 ? range(1, 400) : range(401, 500);
            });
        $this->connection->method('executeStatement')->willReturnOnConsecutiveCalls(300, 100);

        $this->createRepository()->dispatchItems(400);

        $this->assertSame([400, 100], $limits);
    }

    public function testDispatchItemsStopsRefillingAfterThreeRounds(): void
    {
        $this->connection->expects($this->exactly(3))->method('fetchFirstColumn')->willReturn([1, 2, 3]);
        $this->connection->method('executeStatement')->willReturn(0);

        $this->createRepository()->dispatchItems(400);
    }

    public function testDispatchItemsReleasesEarlierRoundsWhenALaterRoundFails(): void
    {
        $this->connection->method('fetchFirstColumn')->willReturnOnConsecutiveCalls(
            range(1, 400),
            $this->throwException($this->deadlock())
        );

        $statements = [];
        $this->connection->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $params) use (&$statements): int {
                $statements[] = [$sql, $params];

                return 300;
            }
        );

        try {
            $this->createRepository()->dispatchItems(400);
            $this->fail('The failing round must be rethrown');
        } catch (DeadlockException) {
            $this->assertCount(2, $statements);
            [$releaseSql, $releaseParams] = $statements[1];
            $this->assertStringContainsString('SET dispatched = 0 WHERE dispatched = :dispatchId', $releaseSql);
            $this->assertSame($statements[0][1]['dispatchId'], $releaseParams['dispatchId']);
        }
    }

    public function testDispatchItemsSkipsTheClaimWhenNothingIsDispatchable(): void
    {
        $this->connection->method('fetchFirstColumn')->willReturn([]);
        $this->connection->expects($this->never())->method('executeStatement');

        $this->createRepository()->dispatchItems(400);
    }

    private function createRepository(): IndexQueueRepository
    {
        $timeService = $this->createMock(TimeServiceInterface::class);
        $timeService->method('getCurrentMillisecondTimestamp')->willReturn(self::TIMESTAMP);

        $repository = new IndexQueueRepository(
            $this->createMock(EntityManagerInterface::class),
            $timeService,
            $this->connection,
            $this->createMock(DenormalizerInterface::class),
        );
        $repository->setLogger($this->logger);

        return $repository;
    }

    private function queryBuilder(): DBALQueryBuilder
    {
        $queryBuilder = $this->createMock(DBALQueryBuilder::class);
        $queryBuilder->method('getSQL')->willReturn('SELECT 1');
        $queryBuilder->method('getParameters')->willReturn([]);

        return $queryBuilder;
    }

    private function assetRows(): Generator
    {
        yield ['id' => 7, 'elementType' => 'asset', 'operation' => 'update', 'operationTime' => 1000];
    }

    private function deadlock(): DeadlockException
    {
        return new DeadlockException($this->createMock(DriverException::class), null);
    }
}
