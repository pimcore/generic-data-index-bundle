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
            'fetchAllAssociative' => [],
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
            'fetchAllAssociative' => [
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
            ],
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
            'fetchAllAssociative' => [
                [
                    'elementId' => '100',
                    'elementType' => 'data_object',
                    'elementIndexName' => 'Product',
                    'operation' => 'update',
                    'operationTime' => '1711800000000',
                    'dispatched' => '0',
                ],
            ],
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
