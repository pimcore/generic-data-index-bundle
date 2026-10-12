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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @internal
 */
final class IndexQueueRepositoryTest extends Unit
{
    private Connection $realConnection;

    public function _before(): void
    {
        $this->realConnection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
    }

    public function testGenerateSelectQueryProducesNamedAliases(): void
    {
        $repository = $this->createRepository($this->realConnection);

        $qb = $repository->generateSelectQuery('assets', [
            'elementId' => 'id',
            'elementType' => "'asset'",
            'elementIndexName' => "'asset'",
            'operation' => "'update'",
            'operationTime' => "'1234'",
            'dispatched' => '0',
        ]);

        $sql = $qb->getSQL();

        $this->assertStringContainsString('id AS elementId', $sql);
        $this->assertStringContainsString("'asset' AS elementType", $sql);
        $this->assertStringContainsString("'asset' AS elementIndexName", $sql);
        $this->assertStringContainsString("'update' AS operation", $sql);
        $this->assertStringContainsString("'1234' AS operationTime", $sql);
        $this->assertStringContainsString('0 AS dispatched', $sql);
        $this->assertStringContainsString('FROM assets', $sql);
    }

    public function testGenerateSelectQueryWithColumnReference(): void
    {
        $repository = $this->createRepository($this->realConnection);

        $qb = $repository->generateSelectQuery('objects', [
            'elementId' => 'id',
            'elementType' => "'data_object'",
            'elementIndexName' => 'className',
            'operation' => "'update'",
            'operationTime' => "'5678'",
            'dispatched' => '0',
        ]);

        $sql = $qb->getSQL();

        $this->assertStringContainsString('className AS elementIndexName', $sql);
    }

    public function testGenerateSelectQueryWithCustomIdColumn(): void
    {
        $repository = $this->createRepository($this->realConnection);

        $qb = $repository->generateSelectQuery('object_1', [
            'elementId' => 'oo_id',
            'elementType' => "'data_object'",
            'elementIndexName' => "'MyClass'",
            'operation' => "'update'",
            'operationTime' => "'9999'",
            'dispatched' => '0',
        ]);

        $sql = $qb->getSQL();

        $this->assertStringContainsString('oo_id AS elementId', $sql);
        $this->assertStringContainsString('FROM object_1', $sql);
    }

    public function testGenerateSelectQueryWithWhereParameters(): void
    {
        $repository = $this->createRepository($this->realConnection);

        $qb = $repository->generateSelectQuery(
            'tags_assignment',
            [
                'elementId' => 'cid',
            ],
            [],
            ['ctype', IndexQueueRepository::AND_OPERATOR => 'tagid']
        );

        $sql = $qb->getSQL();

        $this->assertStringContainsString('cid AS elementId', $sql);
        $this->assertStringContainsString('ctype = :ctype', $sql);
        $this->assertStringContainsString('tagid = :tagid', $sql);
    }

    public function testGenerateSelectQueryWithParams(): void
    {
        $repository = $this->createRepository($this->realConnection);

        $qb = $repository->generateSelectQuery(
            'assets',
            [
                'elementId' => 'id',
                'elementType' => "'asset'",
                'elementIndexName' => "'asset'",
                'operation' => "'update'",
                'operationTime' => "'1234'",
                'dispatched' => '0',
            ],
            ['ctype' => 'asset', 'tagid' => 42],
        );

        $params = $qb->getParameters();

        $this->assertSame('asset', $params['ctype']);
        $this->assertSame(42, $params['tagid']);
    }

    public function testGenerateSelectQueryWithSubsetOfAliases(): void
    {
        $repository = $this->createRepository($this->realConnection);

        $qb = $repository->generateSelectQuery('tags_assignment', [
            'elementId' => 'cid',
        ]);

        $sql = $qb->getSQL();

        $this->assertStringContainsString('cid AS elementId', $sql);
        $this->assertStringNotContainsString('elementType', $sql);
        $this->assertStringNotContainsString('elementIndexName', $sql);
    }

    public function testEnqueueBySelectQueryWithEmptyResult(): void
    {
        $connection = $this->makeEmpty(Connection::class, [
            'iterateAssociative' => new \ArrayIterator([]),
        ]);

        $repository = $this->createRepository($connection);

        $qb = $this->makeEmpty(\Doctrine\DBAL\Query\QueryBuilder::class, [
            'getSQL' => 'SELECT 1',
            'getParameters' => [],
        ]);

        // Should not throw -- empty result is a no-op
        $repository->enqueueBySelectQuery($qb);

        // If we get here without exception, the test passes
        $this->assertTrue(true);
    }

    public function testEnqueueBySelectQueryExtractsNamedColumns(): void
    {
        $insertStatements = [];

        $connection = $this->makeEmpty(Connection::class, [
            'iterateAssociative' => new \ArrayIterator([
                [
                    'elementId' => '10',
                    'elementType' => 'asset',
                    'elementIndexName' => 'asset',
                    'operation' => 'update',
                    'operationTime' => '1711800000000',
                    'dispatched' => '0',
                ],
                [
                    'elementId' => '20',
                    'elementType' => 'asset',
                    'elementIndexName' => 'asset',
                    'operation' => 'update',
                    'operationTime' => '1711800000000',
                    'dispatched' => '0',
                ],
            ]),
            'beginTransaction' => null,
            'commit' => null,
            'quoteIdentifier' => function (string $identifier) {
                return '`' . $identifier . '`';
            },
            'executeStatement' => function (string $sql, array $params) use (&$insertStatements) {
                $insertStatements[] = ['sql' => $sql, 'params' => $params];

                return count($params) > 0 ? 2 : 0;
            },
        ]);

        $repository = $this->createRepository($connection);

        $qb = $this->makeEmpty(\Doctrine\DBAL\Query\QueryBuilder::class, [
            'getSQL' => 'SELECT 1',
            'getParameters' => [],
        ]);

        $repository->enqueueBySelectQuery($qb);

        $this->assertNotEmpty($insertStatements, 'Expected at least one INSERT statement');

        $insertSql = $insertStatements[0]['sql'];
        $insertParams = $insertStatements[0]['params'];

        // Verify the INSERT uses the correct column names
        $this->assertStringContainsString('`elementId`', $insertSql);
        $this->assertStringContainsString('`elementType`', $insertSql);
        $this->assertStringContainsString('`elementIndexName`', $insertSql);
        $this->assertStringContainsString('`operation`', $insertSql);
        $this->assertStringContainsString('`operationTime`', $insertSql);

        // Verify both element IDs are included in the params
        $this->assertContains('10', $insertParams);
        $this->assertContains('20', $insertParams);

        // Verify the element type is passed correctly
        $this->assertContains('asset', $insertParams);

        // Verify the operation is passed correctly
        $this->assertContains('update', $insertParams);
    }

    public function testEnqueueBySelectQueryExtractsDataObjectNamedColumns(): void
    {
        $insertStatements = [];

        $connection = $this->makeEmpty(Connection::class, [
            'iterateAssociative' => new \ArrayIterator([
                [
                    'elementId' => '100',
                    'elementType' => 'data_object',
                    'elementIndexName' => 'Product',
                    'operation' => 'update',
                    'operationTime' => '1711800000000',
                    'dispatched' => '0',
                ],
            ]),
            'beginTransaction' => null,
            'commit' => null,
            'quoteIdentifier' => function (string $identifier) {
                return '`' . $identifier . '`';
            },
            'executeStatement' => function (string $sql, array $params) use (&$insertStatements) {
                $insertStatements[] = ['sql' => $sql, 'params' => $params];

                return 1;
            },
        ]);

        $repository = $this->createRepository($connection);

        $qb = $this->makeEmpty(\Doctrine\DBAL\Query\QueryBuilder::class, [
            'getSQL' => 'SELECT 1',
            'getParameters' => [],
        ]);

        $repository->enqueueBySelectQuery($qb);

        $this->assertNotEmpty($insertStatements);

        $insertParams = $insertStatements[0]['params'];

        // Verify the element ID is extracted correctly
        $this->assertContains('100', $insertParams);

        // Verify the elementIndexName (class name) is extracted correctly
        $this->assertContains('Product', $insertParams);

        // Verify element type
        $this->assertContains('data_object', $insertParams);
    }

    public function testDeleteQueueEntriesMatchesOnIdAndOperationTime(): void
    {
        $executedQueries = [];

        $connection = $this->makeEmpty(Connection::class, [
            'quote' => function (string $value) {
                return "'" . $value . "'";
            },
            'quoteIdentifier' => function (string $identifier) {
                return '`' . $identifier . '`';
            },
            'executeQuery' => function (string $sql) use (&$executedQueries) {
                $executedQueries[] = $sql;

                return $this->makeEmpty(\Doctrine\DBAL\Result::class);
            },
        ]);

        $repository = $this->createRepository($connection);

        $entry1 = new IndexQueue();
        $entry1->setId(42);
        $entry1->setOperationTime('1711800000000');

        $entry2 = new IndexQueue();
        $entry2->setId(99);
        $entry2->setOperationTime('1711800001000');

        $repository->deleteQueueEntries([$entry1, $entry2]);

        $this->assertCount(1, $executedQueries);
        $sql = $executedQueries[0];

        // Must match on BOTH id AND operationTime to prevent race condition
        $this->assertStringContainsString('(`id`, `operationTime`) IN', $sql);
        $this->assertStringContainsString("('42', '1711800000000')", $sql);
        $this->assertStringContainsString("('99', '1711800001000')", $sql);
    }

    public function testDeleteQueueEntriesDoesNotDeleteRequeuedEntries(): void
    {
        // This test documents WHY the operationTime guard exists:
        // If an element is re-queued (e.g., saved again) while being processed,
        // the ON DUPLICATE KEY UPDATE changes the operationTime on the existing row.
        // The DELETE must NOT match the re-queued entry because the operationTime
        // no longer matches what was fetched during processing.

        $executedQueries = [];

        $connection = $this->makeEmpty(Connection::class, [
            'quote' => function (string $value) {
                return "'" . $value . "'";
            },
            'quoteIdentifier' => function (string $identifier) {
                return '`' . $identifier . '`';
            },
            'executeQuery' => function (string $sql) use (&$executedQueries) {
                $executedQueries[] = $sql;

                return $this->makeEmpty(\Doctrine\DBAL\Result::class);
            },
        ]);

        $repository = $this->createRepository($connection);

        // Simulate an entry that was fetched with operationTime=1000
        // but has since been re-queued with operationTime=2000
        $entry = new IndexQueue();
        $entry->setId(42);
        $entry->setOperationTime('1000'); // original time when fetched

        $repository->deleteQueueEntries([$entry]);

        $sql = $executedQueries[0];

        // The DELETE uses the ORIGINAL operationTime ('1000'), not the new one ('2000').
        // In the database, the row now has operationTime=2000, so the tuple (42, '1000')
        // will NOT match, and the re-queued entry will survive.
        $this->assertStringContainsString("('42', '1000')", $sql);
        $this->assertStringNotContainsString('WHERE `id` IN', $sql, 'Must not use id-only matching');
    }

    private function createRepository(Connection $connection): IndexQueueRepository
    {
        return new IndexQueueRepository(
            $this->makeEmpty(EntityManagerInterface::class),
            $this->makeEmpty(TimeServiceInterface::class),
            $connection,
            $this->makeEmpty(DenormalizerInterface::class)
        );
    }
}
