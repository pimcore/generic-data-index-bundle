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

                $sql = sprintf(
                    'SELECT * FROM %s WHERE %s = :dispatchId FOR UPDATE SKIP LOCKED',
                    $this->connection->quoteIdentifier(IndexQueue::TABLE),
                    $this->connection->quoteIdentifier('dispatched')
                );

                return $this->connection->executeQuery(
                    $sql,
                    ['dispatchId' => $dispatchId]
                )->fetchAllAssociative();
            }

            $sql = sprintf(
                'SELECT * FROM %s WHERE %s = 0 ORDER BY %s ASC LIMIT %s FOR UPDATE SKIP LOCKED',
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
        foreach (array_chunk($entries, 500) as $chunk) {
            $condition = [];

            /** @var IndexQueue $entry */
            foreach ($chunk as $entry) {
                $condition[] = sprintf(
                    '(elementId = %s AND elementType = %s and operationTime = %s)',
                    $this->connection->quote((string)$entry->getElementId()),
                    $this->connection->quote($entry->getElementType()),
                    $this->connection->quote($entry->getOperationTime())
                );
            }

            $condition = '(' . implode(' OR ', $condition) . ')';

            //delete handled entry from queue table
            $this->connection->executeQuery('DELETE FROM ' . IndexQueue::TABLE . ' WHERE ' . $condition);
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

        return $this->denormalizer->denormalize($entry, IndexQueue::class);
    }

    public function generateSelectQuery(
        string $tableName,
        array $fields,
        string $idField = 'id',
        array $params = [],
        array $whereParameters = []
    ): DBALQueryBuilder {
        $fields = $this->quoteParameters($fields);
        array_unshift($fields, $idField);

        $qb = $this->connection->createQueryBuilder()
            ->addSelect(...$fields)
            ->from($tableName);

        $this->addWhereStatements($qb, $whereParameters);

        if (!empty($params)) {
            $params = $this->quoteParameters($params);
            $qb->setParameters($params);
        }

        return $qb;
    }

    /**
     * @throws DBALException
     */
    public function enqueueBySelectQuery(
        DBALQueryBuilder $queryBuilder
    ): void {
        $result = $this->connection->fetchAllAssociative($queryBuilder->getSQL(), $queryBuilder->getParameters());
        if (empty($result)) {
            return;
        }

        [
            $ids,
            $elementType,
            $operation,
            $operationTime,
            $elementIndexName
        ] = $this->getValuesFromSqlResult($result);

        foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
            $effectiveChunkSize = count($chunk);

            try {
                $this->connection->beginTransaction();

                $affectedRows = $this->insertFromChunk(
                    $chunk,
                    $elementType,
                    $operation,
                    $operationTime,
                    $elementIndexName
                );

                if ($affectedRows < $effectiveChunkSize) {
                    $this->updateFromChunk(
                        $chunk,
                        $elementType,
                        $operation,
                        $operationTime
                    );
                }

                $this->connection->commit();
            } catch (Throwable $e) {
                $this->connection->rollBack();

                throw $e;
            }
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

        $values = [];
        foreach ($enqueueItemList as $item) {
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

    /**
     * @throws DBALException
     */
    public function dispatchItems(
        int $limit
    ): int {
        $dispatchId = $this->timeService->getCurrentMillisecondTimestamp();

        // Executed as direct SQL statement as doctrine ORM does not support LIMIT in UPDATE queries
        $this->connection->executeQuery(
            'UPDATE ' . IndexQueue::TABLE .
            ' SET dispatched = :dispatchId WHERE dispatched < :dispatched LIMIT ' . $limit,

            [
                'dispatchId' => $dispatchId,
                'dispatched' => $dispatchId - 60*60*24*1000,
            ]
        );

        return $dispatchId;
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

    private function quoteParameters(array $parameters): array
    {
        return array_map(
            function ($parameter) {
                if (is_string($parameter)) {
                    return $this->connection->quote($parameter);
                }

                return $parameter;
            },
            $parameters
        );
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
        int $operationTime
    ): int {
        if (empty($chunk)) {
            return 0;
        }

        $placeholders = str_repeat('?,', count($chunk) - 1) . '?';
        $updateSql = sprintf(
            'UPDATE %s SET %s = ?, %s = ?, %s = 0 WHERE %s IN (%s) AND %s = ?',
            $this->connection->quoteIdentifier(IndexQueue::TABLE),
            $this->connection->quoteIdentifier('operation'),
            $this->connection->quoteIdentifier('operationTime'),
            $this->connection->quoteIdentifier('dispatched'),
            $this->connection->quoteIdentifier('elementId'),
            $placeholders,
            $this->connection->quoteIdentifier('elementType')
        );

        $updateParams = array_merge(
            [$operation, $operationTime],
            $chunk,
            [$elementType]
        );

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
            $insertParams = array_merge($insertParams, [
                $id,
                $elementType,
                $elementIndexName,
                $operation,
                $operationTime,
            ]);
        }

        return $this->connection->executeStatement($insertSql, $insertParams);
    }

    /*
    * @TODO: Refactor to avoid that all values have to be in a specific order. Either use aliases or pass the keys as parameters.
    * Both things can be considered breaking changes.
    */
    private function getValuesFromSqlResult(array $result): array
    {
        if (empty($result)) {
            return [];
        }

        $firstRow = $result[0];
        $keys = array_keys($firstRow);
        $columnCount = count($keys);

        $ids = array_column($result, $keys[0]);
        $elementType = $firstRow[$keys[1]];

        $elementIndexName = match ($elementType) {
            ElementType::ASSET->value, ElementType::DOCUMENT->value => $elementType,
            ElementType::DATA_OBJECT->value => $firstRow['className'] ??
            $firstRow[
                $keys[2]
            ] ??
            null,
            default => null,
        };

        $operation = $firstRow[
            $keys[
                    $columnCount > 5 ? 3 : 2
            ]
        ];

        $operationTime = (int)$firstRow[
            $keys[
                $columnCount > 5 ? 4 : 3
            ]
        ];

        return [
            $ids,
            $elementType,
            $operation,
            $operationTime,
            $elementIndexName,
        ];
    }
}
