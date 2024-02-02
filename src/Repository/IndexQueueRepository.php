<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class IndexQueueRepository
{
    use LoggerAwareTrait;

    public const AND_OPERATOR = 'and';

    public const OR_OPERATOR = 'or';

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
        } catch(NonUniqueResultException) {
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

    public function getUnhandledIndexQueueEntries(bool $dispatch = false, int $limit = 100000): array
    {
        try {
            if ($dispatch) {

                $dispatchId = $this->dispatchItems($limit);

                return $this->createQueryBuilder('q')
                    ->where('q.dispatched = :dispatchId')
                    ->setParameter(':dispatchId', $dispatchId)
                    ->getQuery()
                    ->getArrayResult();
            }

            return $this->createQueryBuilder('q')
                ->orderBy('q.operationTime')
                ->setMaxResults($limit)
                ->getQuery()
                ->getArrayResult();

        } catch (Exception $e) {
            $this->logger->error('getUnhandledIndexQueueEntries failed! Error: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * @param IndexQueue[] $entries
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function deleteQueueEntries(array $entries): void
    {
        foreach(array_chunk($entries, 500) as $chunk) {
            $condition = [];

            /** @var IndexQueue $entry */
            foreach($chunk as $entry) {
                $condition[] = sprintf(
                    '(elementId = %s AND elementType = %s and operationTime = %s)',
                    $this->connection->quote($entry->getElementId()),
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
            ->select($fields)
            ->from($tableName);

        $this->addWhereStatements($qb, $whereParameters);

        if (!empty($params)) {
            $params = $this->quoteParameters($params);
            $qb->setParameters($params);
        }

        return $qb;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function enqueueBySelectQuery(DBALQueryBuilder $queryBuilder): void
    {
        $sql = <<<SQL
            INSERT INTO 
                %s (elementId, elementType, elementIndexName, operation, operationTime, dispatched) 
                %s 
                ON DUPLICATE KEY 
                UPDATE 
                    operation = VALUES(operation), 
                    operationTime = VALUES(operationTime), 
                    dispatched = VALUES(dispatched)
        SQL;

        $sql = sprintf($sql, IndexQueue::TABLE, $queryBuilder->getSQL());
        $this->connection->executeQuery($sql, $queryBuilder->getParameters());
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function dispatchItems(int $limit): int
    {
        $dispatchId = $this->timeService->getCurrentMillisecondTimestamp();

        $this->connection->executeQuery(
            sql: 'UPDATE ' . IndexQueue::TABLE .
            ' SET dispatched = :dispatchId WHERE dispatched < :dispatched LIMIT ' . $limit,

            params: [
                'dispatchId' => $dispatchId,
                'dispatched' => $dispatchId - 60*60*24*1000,
            ]
        );

        return $dispatchId;
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
}
