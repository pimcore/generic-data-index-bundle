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

namespace Pimcore\Bundle\GenericDataIndexBundle\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\HitData;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Throwable;

final class IndexQueueRepository
{
    use LoggerAwareTrait;

    public const AND_OPERATOR = 'and';

    public const OR_OPERATOR = 'or';

    public const BATCH_SIZE = 500;

    /**
     * Factor reserving the low-order digits of a dispatch id for a random
     * uniqueness suffix, while the millisecond timestamp stays in the
     * high-order digits. See generateDispatchId().
     */
    private const DISPATCH_ID_FACTOR = 1_000_000;

    /**
     * How often an enqueue chunk is written before a deadlock is given up on. Concurrent enqueues
     * of overlapping elements (e.g. saves moving assets between objects next to update:index) can
     * deadlock each other; InnoDB then rolls back one of them, which is safe to replay.
     */
    private const MAX_CHUNK_ATTEMPTS = 3;

    private const CHUNK_RETRY_BASE_DELAY_MICROSECONDS = 20_000;

    /**
     * Ids claimed per statement, far below the 65,535 placeholders of native prepared statements.
     */
    private const CLAIM_CHUNK_SIZE = 5_000;

    /**
     * How often a batch shortened by a concurrent dispatcher is refilled with a fresh pick.
     */
    private const MAX_CLAIM_ROUNDS = 3;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TimeServiceInterface $timeService,
        private readonly Connection $connection,
        private readonly DenormalizerInterface $denormalizer,
    ) {
    }

    public function dispatchableItemExists(): bool
    {
        try {
            $result = $this->createQueryBuilder('q')
                ->select('q.operationTime')
                ->where('q.dispatched = 0')
                ->getQuery()
                ->setMaxResults(1)
                ->getOneOrNullResult();

            return $result !== null;
        } catch (NonUniqueResultException) {
            return true;
        }
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countIndexQueueEntries(): int
    {
        return (int)$this->createQueryBuilder('q')
            ->select('count(q)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getUnhandledIndexQueueEntries(
        bool $dispatch = false,
        int $limit = 100000
    ): array {
        try {
            if ($dispatch) {
                $dispatchId = $this->dispatchItems($limit);

                // No row locking required: dispatchItems() has already claimed the
                // rows for this worker by stamping them with the unique $dispatchId,
                // so this query only ever reads its own, exclusively-owned rows.
                $sql = sprintf(
                    'SELECT * FROM %s WHERE %s = :dispatchId',
                    $this->connection->quoteIdentifier(IndexQueue::TABLE),
                    $this->connection->quoteIdentifier('dispatched')
                );

                return $this->connection->executeQuery(
                    $sql,
                    ['dispatchId' => $dispatchId]
                )->fetchAllAssociative();
            }

            // Non-dispatching read ("peek"): returns unhandled entries without
            // claiming them. As a pure read it neither needs nor should hold row
            // locks. Concurrency-safe claiming is handled by the $dispatch branch
            // above via dispatchItems().
            $sql = sprintf(
                'SELECT * FROM %s WHERE %s = 0 ORDER BY %s ASC LIMIT %s',
                $this->connection->quoteIdentifier(IndexQueue::TABLE),
                $this->connection->quoteIdentifier('dispatched'),
                $this->connection->quoteIdentifier('operationTime'),
                $limit
            );

            return $this->connection->executeQuery(
                $sql
            )->fetchAllAssociative();
        } catch (Exception $e) {
            $this->logger->error('getUnhandledIndexQueueEntries failed! Error: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * @param IndexQueue[] $entries
     *
     * @throws DBALException
     */
    public function deleteQueueEntries(array $entries): void
    {
        $chunks = array_chunk($entries, self::BATCH_SIZE);
        foreach ($chunks as $chunk) {
            $tuples = array_map(
                fn (IndexQueue $entry) => sprintf(
                    '(%s, %s)',
                    $this->connection->quote((string)$entry->getId()),
                    $this->connection->quote($entry->getOperationTime())
                ),
                $chunk
            );

            $this->connection->executeQuery(
                sprintf(
                    'DELETE FROM %s WHERE (%s, %s) IN (%s) ORDER BY %s ASC LIMIT %d',
                    $this->connection->quoteIdentifier(IndexQueue::TABLE),
                    $this->connection->quoteIdentifier('id'),
                    $this->connection->quoteIdentifier('operationTime'),
                    implode(',', $tuples),
                    $this->connection->quoteIdentifier('id'),
                    self::BATCH_SIZE
                )
            );
        }
    }

    /**
     * Removes every entry of one index, e.g. the entries of a deleted class once its index is gone.
     *
     * @return int the number of removed entries
     *
     * @throws DBALException
     */
    public function deleteQueueEntriesByIndexName(ElementType $elementType, string $elementIndexName): int
    {
        return (int)$this->connection->executeStatement(
            sprintf(
                'DELETE FROM %s WHERE %s = :elementType AND %s = :elementIndexName',
                $this->connection->quoteIdentifier(IndexQueue::TABLE),
                $this->connection->quoteIdentifier('elementType'),
                $this->connection->quoteIdentifier('elementIndexName')
            ),
            [
                'elementType' => $elementType->value,
                'elementIndexName' => $elementIndexName,
            ]
        );
    }

    /**
     * @throws ExceptionInterface
     */
    public function denormalizeDatabaseEntry(array $entry): IndexQueue
    {
        //bigint field potentially exceed max php int values on 32 bit systems, therefore this is handled as string
        $entry['operationTime'] = (string)$entry['operationTime'];
        $entry['dispatched'] = (string)$entry['dispatched'];
        $entry['id'] = (string)$entry['id'];

        return $this->denormalizer->denormalize($entry, IndexQueue::class);
    }

    /**
     * @param array<string, string> $columnAliases Associative array mapping alias names to SQL expressions
     * @param array<string, mixed>  $params        Query parameters for setParameters()
     * @param array<int|string, string> $whereParameters Column names for WHERE clauses; keys may be numeric
     *                                                   or operator constants (AND_OPERATOR, OR_OPERATOR)
     */
    public function generateSelectQuery(
        string $tableName,
        array $columnAliases,
        array $params = [],
        array $whereParameters = []
    ): DBALQueryBuilder {
        $selectExpressions = [];
        foreach ($columnAliases as $alias => $expression) {
            /** @phpstan-ignore function.impossibleType (runtime guard for callers without static analysis) */
            if (is_int($alias)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Column aliases must be an associative array with string keys '
                        . '(alias => expression), got numeric key %d for expression "%s".',
                        $alias,
                        $expression
                    )
                );
            }
            $selectExpressions[] = $expression . ' AS ' . $alias;
        }

        $qb = $this->connection->createQueryBuilder()
            ->addSelect(...$selectExpressions)
            ->from($tableName);

        $this->addWhereStatements($qb, $whereParameters);

        if (!empty($params)) {
            $qb->setParameters($params);
        }

        return $qb;
    }

    /**
     * @throws DBALException|Throwable
     */
    public function enqueueBySelectQuery(DBALQueryBuilder $queryBuilder): void
    {
        $firstRow = null;
        $idKey = null;
        $chunk = [];

        $iterator = $this->connection->iterateAssociative(
            $queryBuilder->getSQL(),
            $queryBuilder->getParameters()
        );

        foreach ($iterator as $row) {
            if ($firstRow === null) {
                $firstRow = $row;
                $idKey = array_key_first($row);
            }
            $chunk[] = $row[$idKey];
            if (count($chunk) === self::BATCH_SIZE) {
                $this->flushChunk($chunk, $firstRow);
                $chunk = [];
            }
        }

        // flush remaining
        if (!empty($chunk)) {
            $this->flushChunk($chunk, $firstRow);
        }
    }

    /**
     * @throws DBALException
     *
     * @param HitData[] $enqueueItemList
     * @param bool $keepQueuedDelete a queued DELETE entry of an element is left untouched instead of overwritten
     */
    public function enqueueByItemList(
        array $enqueueItemList,
        IndexQueueOperation $operation,
        int $operationTime,
        bool $keepQueuedDelete = false
    ): void {
        if (empty($enqueueItemList)) {
            return;
        }

        $update = <<<SQL
                    operation = VALUES(operation),
                    operationTime = VALUES(operationTime),
                    dispatched = VALUES(dispatched)
        SQL;
        if ($keepQueuedDelete) {
            // MySQL applies the assignments left to right: operation must be assigned last, the other columns
            // compare against its original value.
            $delete = $this->connection->quote(IndexQueueOperation::DELETE->value);
            $update = <<<SQL
                    operationTime = IF(operation = $delete, operationTime, VALUES(operationTime)),
                    dispatched = IF(operation = $delete, dispatched, VALUES(dispatched)),
                    operation = IF(operation = $delete, operation, VALUES(operation))
        SQL;
        }

        $sql = <<<SQL
            INSERT INTO
                %s (elementId, elementType, elementIndexName, operation, operationTime, dispatched)
                VALUES %s
                ON DUPLICATE KEY
                UPDATE
        $update
        SQL;

        $chunks = array_chunk($enqueueItemList, self::BATCH_SIZE);
        foreach ($chunks as $chunk) {
            $values = [];
            foreach ($chunk as $item) {
                $values[] = sprintf(
                    '(%s, %s, %s, %s, %s, 0)',
                    $this->connection->quote($item->getId()),
                    $this->connection->quote($item->getElementType()),
                    $this->connection->quote($item->getIndex()),
                    $this->connection->quote($operation->value),
                    $operationTime
                );
            }
            $this->connection->executeQuery(
                sprintf($sql, IndexQueue::TABLE, implode(',', $values))
            );
        }
    }

    /**
     * @throws DBALException
     */
    public function dispatchItems(
        int $limit
    ): int {
        $timestamp = $this->timeService->getCurrentMillisecondTimestamp();
        $dispatchId = $this->generateDispatchId($timestamp);

        // 24h staleness threshold, scaled to the dispatch id encoding so
        // that unhandled (0) and stale rows are still re-dispatched.
        $threshold = ($timestamp - 60*60*24*1000) * self::DISPATCH_ID_FACTOR;

        // Pick the rows with a non-locking read and claim them by primary key afterwards. A ranged
        // "UPDATE ... WHERE dispatched < :threshold LIMIT n" locks the scanned range of the
        // dispatched index, which deadlocks with enqueues resetting rows to dispatched = 0.
        // A concurrent dispatcher can take some of the picked rows. The batch is then refilled,
        // because a short batch ends the drain loop of QueueMessageService while rows are pending.
        $claimed = 0;

        try {
            for ($round = 1; $round <= self::MAX_CLAIM_ROUNDS && $claimed < $limit; $round++) {
                $ids = $this->pickDispatchableIds($limit - $claimed, $threshold);
                if ($ids === []) {
                    break;
                }

                $claimedInRound = $this->claimIds($ids, $dispatchId, $threshold);
                $claimed += $claimedInRound;

                if ($claimedInRound === count($ids)) {
                    break;
                }
            }
        } catch (Exception $e) {
            if ($claimed > 0) {
                $this->releaseClaim($dispatchId);
            }

            throw $e;
        }

        return $dispatchId;
    }

    /**
     * @return list<int> ids in ascending order, so concurrent claims lock rows in the same order
     *
     * @throws DBALException
     */
    private function pickDispatchableIds(int $limit, int $threshold): array
    {
        $ids = $this->connection->fetchFirstColumn(
            sprintf(
                'SELECT %s FROM %s WHERE %s < :threshold LIMIT %d',
                $this->connection->quoteIdentifier('id'),
                $this->connection->quoteIdentifier(IndexQueue::TABLE),
                $this->connection->quoteIdentifier('dispatched'),
                $limit
            ),
            ['threshold' => $threshold]
        );

        $ids = array_map(intval(...), $ids);
        sort($ids);

        return $ids;
    }

    /**
     * Claims the rows that are still dispatchable. The threshold is checked again under the row lock,
     * so a row that another dispatcher claimed in the meantime is skipped instead of being claimed
     * twice. Large batches are claimed in bounded statements within one transaction, so a failure
     * leaves no partial claim.
     *
     * @param list<int> $ids
     *
     * @return int the number of claimed rows
     *
     * @throws DBALException
     */
    private function claimIds(array $ids, int $dispatchId, int $threshold): int
    {
        $sql = sprintf(
            'UPDATE %s SET %s = :dispatchId WHERE %s IN (:ids) AND %s < :threshold',
            $this->connection->quoteIdentifier(IndexQueue::TABLE),
            $this->connection->quoteIdentifier('dispatched'),
            $this->connection->quoteIdentifier('id'),
            $this->connection->quoteIdentifier('dispatched')
        );

        $claimed = 0;
        $this->connection->beginTransaction();

        try {
            foreach (array_chunk($ids, self::CLAIM_CHUNK_SIZE) as $idChunk) {
                $claimed += (int)$this->connection->executeStatement(
                    $sql,
                    [
                        'dispatchId' => $dispatchId,
                        'ids' => $idChunk,
                        'threshold' => $threshold,
                    ],
                    ['ids' => ArrayParameterType::INTEGER]
                );
            }

            $this->connection->commit();
        } catch (Exception $e) {
            $this->connection->rollBack();

            throw $e;
        }

        return $claimed;
    }

    /**
     * Hands the rows of a failed dispatch back, so they are not left claimed until they turn stale.
     */
    private function releaseClaim(int $dispatchId): void
    {
        try {
            $this->connection->executeStatement(
                sprintf(
                    'UPDATE %s SET %s = 0 WHERE %s = :dispatchId',
                    $this->connection->quoteIdentifier(IndexQueue::TABLE),
                    $this->connection->quoteIdentifier('dispatched'),
                    $this->connection->quoteIdentifier('dispatched')
                ),
                ['dispatchId' => $dispatchId]
            );
        } catch (Exception $e) {
            // The original failure is rethrown by the caller; the rows turn stale and are re-dispatched.
            $this->logger->error('Releasing the claim of a failed dispatch failed', [
                'dispatchId' => $dispatchId,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Builds a per-dispatch claim token that stays unique even when several
     * workers dispatch within the same millisecond.
     *
     * The millisecond timestamp is kept in the high-order digits - preserving
     * the chronological ordering the stale-item re-dispatch in dispatchItems()
     * relies on - while a random suffix in the low-order digits guarantees
     * uniqueness within a single millisecond. The result stays well within the
     * unsigned bigint range of the "dispatched" column.
     */
    private function generateDispatchId(int $timestamp): int
    {
        return $timestamp * self::DISPATCH_ID_FACTOR + random_int(0, self::DISPATCH_ID_FACTOR - 1);
    }

    public function resetDispatchedItems(string $dispatchId): void
    {
        $this->createQueryBuilder('iq')
            ->update(IndexQueue::class, 'iq')
            ->set('iq.dispatched', 0)
            ->where('iq.dispatched = :dispatchId')
            ->setParameter('dispatchId', $dispatchId)
            ->getQuery()
            ->execute();

        $this->entityManager->flush();
    }

    private function createQueryBuilder(string $alias): QueryBuilder
    {
        return $this->entityManager->getRepository(IndexQueue::class)
            ->createQueryBuilder($alias);
    }

    private function addWhereStatements(DBALQueryBuilder $queryBuilder, array $whereParameters): DBALQueryBuilder
    {
        foreach ($whereParameters as $operator => $parameter) {
            $predicate = $parameter . ' = :' . $parameter;
            match (true) {
                $operator === self::AND_OPERATOR => $queryBuilder->andWhere($predicate),
                $operator === self::OR_OPERATOR => $queryBuilder->orWhere($predicate),
                default => $queryBuilder->where($predicate),
            };
        }

        return $queryBuilder;
    }

    private function updateFromChunk(
        array $chunk,
        string $elementType,
        string $operation,
        int $operationTime,
        ?string $elementIndexName = null,
    ): int {
        if (empty($chunk)) {
            return 0;
        }

        $placeholders = str_repeat('?,', count($chunk) - 1) . '?';

        $setClauses = sprintf(
            '%s = ?, %s = ?, %s = 0',
            $this->connection->quoteIdentifier('operation'),
            $this->connection->quoteIdentifier('operationTime'),
            $this->connection->quoteIdentifier('dispatched'),
        );

        if ($elementIndexName !== null) {
            $setClauses .= sprintf(', %s = ?', $this->connection->quoteIdentifier('elementIndexName'));
        }

        $updateSql = sprintf(
            'UPDATE %s SET %s WHERE %s IN (%s) AND %s = ?',
            $this->connection->quoteIdentifier(IndexQueue::TABLE),
            $setClauses,
            $this->connection->quoteIdentifier('elementId'),
            $placeholders,
            $this->connection->quoteIdentifier('elementType')
        );

        $updateParams = [$operation, $operationTime];
        if ($elementIndexName !== null) {
            $updateParams[] = $elementIndexName;
        }
        $updateParams = array_merge($updateParams, $chunk, [$elementType]);

        return $this->connection->executeStatement($updateSql, $updateParams);
    }

    private function insertFromChunk(
        array $chunk,
        string $elementType,
        string $operation,
        int $operationTime,
        ?string $elementIndexName = null,
    ): int {
        if (empty($chunk)) {
            return 0;
        }

        $placeholders = str_repeat('(?, ?, ?, ?, ?, 0),', count($chunk) - 1) . '(?, ?, ?, ?, ?, 0)';
        $insertSql = sprintf(
            'INSERT IGNORE INTO %s (%s, %s, %s, %s, %s, %s) VALUES %s',
            $this->connection->quoteIdentifier(IndexQueue::TABLE),
            $this->connection->quoteIdentifier('elementId'),
            $this->connection->quoteIdentifier('elementType'),
            $this->connection->quoteIdentifier('elementIndexName'),
            $this->connection->quoteIdentifier('operation'),
            $this->connection->quoteIdentifier('operationTime'),
            $this->connection->quoteIdentifier('dispatched'),
            $placeholders
        );

        $insertParams = [];
        foreach ($chunk as $id) {
            array_push($insertParams, $id, $elementType, $elementIndexName, $operation, $operationTime);
        }

        return $this->connection->executeStatement($insertSql, $insertParams);
    }

    /**
     * Extracts values from a result chunk that uses named column aliases.
     * Expected aliases: elementId, elementType, elementIndexName, operation, operationTime.
     *
     * @param list<string|int>     $ids      Element IDs (flat list) for the current chunk
     * @param array<string, mixed> $metaData First row of the query result, providing element type, operation, etc.
     *
     * @return array{0: list<string|int>, 1: string, 2: string, 3: int, 4: string|null}|array{}
     */
    private function getValuesFromSqlResult(array $ids, array $metaData): array
    {
        if (empty($ids)) {
            return [];
        }

        return [
            $ids,
            $metaData['elementType'],
            $metaData['operation'],
            (int)$metaData['operationTime'],
            $metaData['elementIndexName'] ?? null,
        ];
    }

    /**
     * @param list<string|int> $chunk Element IDs
     * @param array{elementType: string, elementId: string, operation: string, operationTime: string|int, elementIndexName: string} $metaData
     *
     * @throws Throwable
     * @throws DBALException
     */
    private function flushChunk(array $chunk, array $metaData): void
    {
        if (empty($chunk)) {
            return;
        }

        [
            $ids,
            $elementType,
            $operation,
            $operationTime,
            $elementIndexName,

        ] = $this->getValuesFromSqlResult(
            $chunk,
            $metaData
        );

        $outerTransaction = $this->connection->getTransactionNestingLevel() > 0;

        for ($attempt = 1; ; $attempt++) {
            try {
                $this->writeChunk($ids, $elementType, $operation, $operationTime, $elementIndexName);

                return;
            } catch (DeadlockException $e) {
                // Inside an outer transaction the deadlock has rolled back the caller's work as well,
                // so only the caller can replay it.
                if ($outerTransaction || $attempt >= self::MAX_CHUNK_ATTEMPTS) {
                    throw $e;
                }

                $this->logger->warning('Enqueueing index queue chunk deadlocked, retrying', [
                    'elementType' => $elementType,
                    'entries' => count($ids),
                    'attempt' => $attempt,
                ]);

                usleep(self::CHUNK_RETRY_BASE_DELAY_MICROSECONDS * $attempt + random_int(0, 10_000));
            }
        }
    }

    /**
     * @param list<string|int> $ids Element IDs
     *
     * @throws Throwable
     * @throws DBALException
     */
    private function writeChunk(
        array $ids,
        string $elementType,
        string $operation,
        int $operationTime,
        ?string $elementIndexName,
    ): void {
        try {
            $this->connection->beginTransaction();

            $affectedRows = $this->insertFromChunk(
                $ids,
                $elementType,
                $operation,
                $operationTime,
                $elementIndexName
            );

            if ($affectedRows < count($ids)) {
                $this->updateFromChunk(
                    $ids,
                    $elementType,
                    $operation,
                    $operationTime,
                    $elementIndexName,
                );
            }

            $this->connection->commit();
        } catch (Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }
}
