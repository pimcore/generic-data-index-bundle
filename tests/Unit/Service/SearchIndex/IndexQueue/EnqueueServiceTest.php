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
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\EnqueueElementsException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexRebuildMarker;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\RequiredByElementListServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AdapterServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexCompletionNotifierInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexRebuildStateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * The rebuild marker through a full enqueue, with the real repository chunking over a stubbed connection.
 *
 * @internal
 */
final class EnqueueServiceTest extends Unit
{
    /** @var string[] */
    private array $markerCalls = [];

    /** @var string[] */
    private array $notified = [];

    private int $chunksCommitted = 0;

    protected function _before(): void
    {
        $this->markerCalls = [];
        $this->notified = [];
        $this->chunksCommitted = 0;
    }

    public function testACompleteEnqueueAdvancesTheMarkerAndChecksForCompletion(): void
    {
        $this->createService(rows: 3, failAfterRows: false)->enqueueAssets();

        $this->assertSame(1, $this->chunksCommitted);
        $this->assertSame(['markEnqueuing', 'markEnqueued'], $this->markerCalls);
        $this->assertSame(['asset'], $this->notified, 'The enqueue checks whether the workers were faster');
    }

    /**
     * The chunks are committed one by one: a failure after the first leaves part of the rebuild queued. The
     * marker must stay in its queueing phase, so the status keeps reporting the rebuild until a repeated full
     * enqueue replaces it, instead of passing the queued part off as normal indexing lag.
     */
    public function testAFailureAfterACommittedChunkKeepsTheMarkerInItsQueueingPhase(): void
    {
        $service = $this->createService(rows: IndexQueueRepository::BATCH_SIZE, failAfterRows: true);

        try {
            $service->enqueueAssets();
            $this->fail('Expected EnqueueElementsException');
        } catch (EnqueueElementsException $e) {
            $this->assertStringContainsString('connection lost', $e->getMessage());
        }

        $this->assertSame(1, $this->chunksCommitted, 'The first chunk is committed before the failure');
        $this->assertSame(['markEnqueuing'], $this->markerCalls, 'Neither advanced nor removed');
        $this->assertSame([], $this->notified);
    }

    /**
     * @param int $rows source rows the select yields before it either ends or fails
     */
    private function createService(int $rows, bool $failAfterRows): EnqueueService
    {
        $connection = null;
        $connection = $this->makeEmpty(Connection::class, [
            'createQueryBuilder' => static function () use (&$connection): QueryBuilder {
                return new QueryBuilder($connection);
            },
            'getDatabasePlatform' => new MySQLPlatform(),
            'quoteIdentifier' => static fn (string $identifier): string => '`' . $identifier . '`',
            'iterateAssociative' => static function () use ($rows, $failAfterRows): Generator {
                for ($id = 1; $id <= $rows; $id++) {
                    yield [
                        'elementId' => $id,
                        'elementType' => 'asset',
                        'elementIndexName' => 'asset',
                        'operation' => 'update',
                        'operationTime' => '1',
                        'dispatched' => '0',
                    ];
                }
                if ($failAfterRows) {
                    // DBAL 4 declares its base exception as an interface
                    throw new class('connection lost') extends RuntimeException implements DBALException {
                    };
                }
            },
            'beginTransaction' => static function (): void {
            },
            'commit' => function (): void {
                $this->chunksCommitted++;
            },
            'rollBack' => static function (): void {
            },
            'executeStatement' => static fn (): int => IndexQueueRepository::BATCH_SIZE,
        ]);
        $timeService = $this->makeEmpty(TimeServiceInterface::class, ['getCurrentMillisecondTimestamp' => 1]);
        $repository = new IndexQueueRepository(
            $this->makeEmpty(EntityManagerInterface::class),
            $timeService,
            $connection,
            $this->makeEmpty(DenormalizerInterface::class)
        );
        $rebuildState = $this->makeEmpty(IndexRebuildStateServiceInterface::class, [
            'markEnqueuing' => function (): IndexRebuildMarker {
                $this->markerCalls[] = 'markEnqueuing';

                return new IndexRebuildMarker(1, false, 'token');
            },
            'markEnqueued' => function (): bool {
                $this->markerCalls[] = 'markEnqueued';

                return true;
            },
            'claim' => function (): bool {
                $this->markerCalls[] = 'claim';

                return true;
            },
        ]);
        $notifier = $this->makeEmpty(IndexCompletionNotifierInterface::class, [
            'afterEnqueue' => function (IndexStateTarget $target): void {
                $this->notified[] = $target->getKey();
            },
        ]);

        return new EnqueueService(
            $repository,
            $timeService,
            new QueueMessagesDispatcher($this->makeEmpty(MessageBusInterface::class), $repository),
            $this->makeEmpty(AdapterServiceInterface::class),
            $this->makeEmpty(RequiredByElementListServiceInterface::class),
            $rebuildState,
            $notifier
        );
    }
}
