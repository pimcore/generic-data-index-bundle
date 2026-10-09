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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\IndexQueue;

use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessageService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @internal
 */
final class QueueMessageServiceTest extends Unit
{
    private QueueMessageService $queueMessageService;

    public function _before(): void
    {
        $this->queueMessageService = new QueueMessageService(
            $this->getEmptyQueueRepository(),
            $this->makeEmpty(MessageBusInterface::class)
        );
    }

    public function testGetMaxBatchSizeWithOneWorker(): void
    {
        $this->assertSame(
            40,
            $this->queueMessageService->getMaxBatchSize(
                100,
                1,
                10,
                40
            )
        );
    }

    public function testGetMaxBatchSizeWithMultipleWorkers(): void
    {
        $this->assertSame(
            50,
            $this->queueMessageService->getMaxBatchSize(
                250,
                5,
                5,
                400
            )
        );
    }

    public function testGetMaxBatchSizeWithOneWorkerAndFewItems(): void
    {
        $this->assertSame(
            50,
            $this->queueMessageService->getMaxBatchSize(
                20,
                1,
                10,
                50
            )
        );
    }

    public function testGetMaxBatchSizeWithMultipleWorkersAndFewItems(): void
    {
        $this->assertSame(
            10,
            $this->queueMessageService->getMaxBatchSize(
                20,
                2,
                5,
                500
            )
        );
    }

    /**
     * Raw DBAL rows carry the "dispatched" bigint as int (native PDO types), while
     * IndexQueueRepository::resetDispatchedItems() expects a string.
     */
    public function testHandleMessageResetsDispatchedItemsWithIntDispatchIdWhenDispatchFails(): void
    {
        $dispatchId = 1760000000000123;

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['id' => 1, 'elementType' => 'object', 'dispatched' => $dispatchId],
        ]);
        $connection = $this->createMock(Connection::class);
        $connection->method('executeQuery')->willReturn($result);

        $resetDispatchIds = [];
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('update')->willReturnSelf();
        $queryBuilder->method('set')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnCallback(
            function (string $key, mixed $value) use ($queryBuilder, &$resetDispatchIds): QueryBuilder {
                $resetDispatchIds[] = $value;

                return $queryBuilder;
            }
        );
        $queryBuilder->method('getQuery')->willReturn($this->createMock(Query::class));
        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('createQueryBuilder')->willReturn($queryBuilder);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($entityRepository);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->method('dispatch')->willThrowException(new Exception('transport down'));

        $indexQueueRepository = new IndexQueueRepository(
            $entityManager,
            $this->makeEmpty(TimeServiceInterface::class),
            $connection,
            $this->makeEmpty(DenormalizerInterface::class)
        );
        $indexQueueRepository->setLogger(new NullLogger());

        $queueMessageService = new QueueMessageService($indexQueueRepository, $messageBus);
        $queueMessageService->setLogger(new NullLogger());

        $queueMessageService->handleMessage(1, 10);

        $this->assertSame([(string)$dispatchId], $resetDispatchIds);
    }

    private function getEmptyQueueRepository(): IndexQueueRepository
    {
        return new IndexQueueRepository(
            $this->makeEmpty(EntityManagerInterface::class),
            $this->makeEmpty(TimeServiceInterface::class),
            $this->makeEmpty(Connection::class),
            $this->makeEmpty(DenormalizerInterface::class)
        );
    }
}
