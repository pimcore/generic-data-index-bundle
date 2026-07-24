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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
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
        if (!empty($chunk) && $firstRow !== null) {
            $this->flushChunk($chunk, $firstRow);
        }
    }

    /**
     * @throws DBALException
     *
     * @param HitData[] $enqueueItemList
     */
    public function enqueueByItemList(array $enqueueItemList, IndexQueueOperation $operation, int $operationTime): void
    {
        if (empty($enqueueItemList)) {
            return;
        }

        $sql = <<<SQL
            INSERT INTO
                %s (elementId, elementType, elementIndexName, operation, operationTime, dispatched)
                VALUES %s
                ON DUPLICATE KEY
                UPDATE
                    operation = VALUES(operation),
                    operationTime = VALUES(operationTime),
                    dispatched = VALUES(dispatched)
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

        // Executed as direct SQL statement as doctrine ORM does not support LIMIT in UPDATE queries
        $this->connection->executeQuery(
            'UPDATE ' . IndexQueue::TABLE .
            ' SET dispatched = :dispatchId WHERE dispatched < :threshold LIMIT ' . $limit,

            [
                'dispatchId' => $dispatchId,
                // 24h staleness threshold, scaled to the dispatch id encoding so
                // that unhandled (0) and stale rows are still re-dispatched.
                'threshold' => ($timestamp - 60*60*24*1000) * self::DISPATCH_ID_FACTOR,
            ]
        );

        return $dispatchId;
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

        $effectiveChunkSize = count($chunk);

        try {
            $this->connection->beginTransaction();

            $affectedRows = $this->insertFromChunk(
                $ids,
                $elementType,
                $operation,
                $operationTime,
                $elementIndexName
            );

            if ($affectedRows < $effectiveChunkSize) {
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
