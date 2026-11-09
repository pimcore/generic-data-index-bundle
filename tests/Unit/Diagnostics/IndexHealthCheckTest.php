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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Diagnostics;

use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Diagnostics\IndexHealthCheck;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Status\IndexQueueStatusService;
use Pimcore\Contracts\Diagnostics\CheckInterface;
use Pimcore\Contracts\Diagnostics\DataClass;
use Pimcore\Contracts\Diagnostics\DataContract;
use Pimcore\SearchClient\SearchClientInterface;
use ReflectionClass;

/**
 * @internal
 */
final class IndexHealthCheckTest extends Unit
{
    private Connection $connection;

    /** @var list<array<string, mixed>> */
    private array $catCalls = [];

    public function _before(): void
    {
        if (!interface_exists(CheckInterface::class)) {
            $this->markTestSkipped('The diagnostics contract ships with Pimcore 2026.4.');
        }

        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(
            'CREATE TABLE ' . IndexQueue::TABLE . ' (id INTEGER PRIMARY KEY AUTOINCREMENT)'
        );
    }

    public function testIdIsPrefixedWithTheBundleName(): void
    {
        $this->assertSame('generic_data_index.index_health', $this->createCheck([])->getId());
    }

    public function testIndicesOfThePrefixAreReadAsJsonWithByteSizes(): void
    {
        $this->createCheck([], indexPrefix: 'shop_')->run();

        $this->assertSame([['index' => 'shop_*', 'format' => 'json', 'bytes' => 'b']], $this->catCalls);
    }

    public function testGreenIndicesAreSummedUpWithoutFindings(): void
    {
        $result = $this->createCheck([
            $this->row('pimcore_asset-odd', 'green', '10', '2048'),
            $this->row('pimcore_data-object_car-even', 'green', '5', '1024'),
        ])->run();

        $this->assertSame([
            'index_count' => 2,
            'indices_red' => 0,
            'indices_yellow' => 0,
            'documents_total' => 15,
            'size_bytes_total' => 3072,
        ], $result->metrics);
        $this->assertSame([], $result->findings);
        $this->assertSame([], $result->notAvailable);
        DiagnosticsAssertions::assertNoHostSchemeOrCredential($result);
    }

    public function testRedAndYellowIndicesAreCountedAndReported(): void
    {
        $result = $this->createCheck([
            $this->row('pimcore_asset-odd', 'red', '1', '10'),
            $this->row('pimcore_document-even', 'yellow', '2', '20'),
            $this->row('pimcore_data-object_car-odd', 'green', '3', '30'),
        ])->run();

        $this->assertSame(1, $result->metrics['indices_red']);
        $this->assertSame(1, $result->metrics['indices_yellow']);
        $this->assertSame([
            [
                'code' => 'index_not_green',
                'severity' => 'critical',
                'message' => 'The index is not green.',
                'subject' => 'pimcore_asset-odd',
            ],
            [
                'code' => 'index_not_green',
                'severity' => 'warning',
                'message' => 'The index is not green.',
                'subject' => 'pimcore_document-even',
            ],
        ], $result->toArray()['findings']);
    }

    public function testIndexNamesThatDoNotMatchThePatternAreNotUsedAsSubject(): void
    {
        $result = $this->createCheck([
            $this->row('pimcore_<script>', 'red', '0', '0'),
            $this->row('_pimcore', 'red', '0', '0'),
            $this->row('pimcore_' . str_repeat('a', 300), 'red', '0', '0'),
            $this->row('pimcore_user@host:9200', 'yellow', '0', '0'),
            $this->row("pimcore_asset\n", 'yellow', '0', '0'),
        ])->run();

        $findings = $result->toArray()['findings'];
        $this->assertCount(5, $findings);
        foreach ($findings as $finding) {
            $this->assertArrayNotHasKey('subject', $finding);
            $this->assertSame('The index is not green.', $finding['message']);
        }
        DiagnosticsAssertions::assertNoHostSchemeOrCredential($result);
    }

    public function testAtMostTwentyIndicesAreReportedButAllAreCounted(): void
    {
        $rows = [];
        for ($i = 0; $i < 25; $i++) {
            $rows[] = $this->row('pimcore_index_' . $i, 'red', '1', '1');
        }

        $result = $this->createCheck($rows)->run();

        $this->assertCount(20, $result->findings);
        $this->assertSame(25, $result->metrics['indices_red']);
        $this->assertSame(25, $result->metrics['index_count']);
    }

    public function testRedIndicesAreReportedBeforeYellowOnesWhenTheCapApplies(): void
    {
        $rows = [];
        for ($i = 0; $i < 20; $i++) {
            $rows[] = $this->row('pimcore_yellow_' . $i, 'yellow', '1', '1');
        }
        $rows[] = $this->row('pimcore_red', 'red', '1', '1');

        $findings = $this->createCheck($rows)->run()->toArray()['findings'];

        $this->assertCount(20, $findings);
        $this->assertSame('critical', $findings[0]['severity']);
        $this->assertSame('pimcore_red', $findings[0]['subject']);
        $this->assertSame('warning', $findings[19]['severity']);
    }

    /**
     * @return array<string, array{0: array<mixed>}>
     */
    public static function malformedRowProvider(): array
    {
        return [
            'empty row' => [[]],
            'missing index name' => [['health' => 'green', 'status' => 'open']],
            'non-string index name' => [['index' => 42, 'health' => 'green', 'status' => 'open']],
            'empty index name' => [['index' => '', 'health' => 'green', 'status' => 'open']],
            'open index without health' => [['index' => 'pimcore_asset', 'status' => 'open']],
            'unknown health' => [['index' => 'pimcore_asset', 'health' => 'purple', 'status' => 'open']],
            'closed index without name' => [['status' => 'close']],
        ];
    }

    /**
     * @dataProvider malformedRowProvider
     *
     * @param array<mixed> $row
     */
    public function testMalformedRowInsideTheListIsNotReadable(array $row): void
    {
        $result = $this->createCheck([$this->row('pimcore_document', 'green', '1', '1'), $row])->run();

        $this->assertSame([['subject' => 'indices', 'reason' => 'not_readable']], $result->toArray()['not_available']);
        $this->assertSame([], $result->metrics);
        $this->assertSame([], $result->findings);
    }

    public function testNoIndexForThePrefixIsAWarningWhenTheQueueTableExists(): void
    {
        $result = $this->createCheck([])->run();

        $this->assertSame(0, $result->metrics['index_count']);
        $this->assertSame([[
            'code' => 'no_indices_for_prefix',
            'severity' => 'warning',
            'message' => 'No search index exists for the configured index prefix.',
        ]], $result->toArray()['findings']);
    }

    public function testNoIndexWithoutTheQueueTableIsNoFinding(): void
    {
        $this->connection->executeStatement('DROP TABLE ' . IndexQueue::TABLE);

        $result = $this->createCheck([])->run();

        $this->assertSame(0, $result->metrics['index_count']);
        $this->assertSame([], $result->findings);
    }

    public function testMissingOrNonNumericValuesCountAsZero(): void
    {
        $result = $this->createCheck([
            ['index' => 'pimcore_closed', 'status' => 'close'],
            $this->row('pimcore_asset', 'green', 'n/a', ''),
        ])->run();

        $this->assertSame(2, $result->metrics['index_count']);
        $this->assertSame(0, $result->metrics['documents_total']);
        $this->assertSame(0, $result->metrics['size_bytes_total']);
        $this->assertSame([], $result->findings);
    }

    public function testClientFailureIsReportedWithoutMetricsAndWithoutTheExceptionMessage(): void
    {
        $result = $this->createCheck(new Exception(
            'Failed to get all indices: No alive nodes. https://admin:secret@search.internal:9200'
        ))->run();

        $this->assertSame([['subject' => 'indices', 'reason' => 'not_readable']], $result->toArray()['not_available']);
        $this->assertSame([], $result->metrics);
        $this->assertSame([], $result->findings);
        DiagnosticsAssertions::assertNoHostSchemeOrCredential($result);
    }

    public function testUnexpectedResponseShapeIsNotReadable(): void
    {
        $result = $this->createCheck(['error' => 'something'])->run();

        $this->assertSame([['subject' => 'indices', 'reason' => 'not_readable']], $result->toArray()['not_available']);
        $this->assertSame([], $result->metrics);
    }

    public function testDataContractIsDeclared(): void
    {
        $attributes = (new ReflectionClass(IndexHealthCheck::class))->getAttributes(DataContract::class);
        $this->assertCount(1, $attributes);

        $contract = $attributes[0]->newInstance();
        $this->assertSame([DataClass::Metric, DataClass::Infrastructure, DataClass::Identifier], $contract->returns);
        $this->assertSame(
            ['document contents', 'search hosts and credentials', 'exception messages'],
            $contract->neverReturns
        );
        $this->assertSame(['search indices of the configured prefix'], $contract->sources);
        $this->assertSame('doc/06_Diagnostics/README.md#index_health', $contract->doc);
    }

    /**
     * @return array<string, string>
     */
    private function row(string $index, string $health, string $docsCount, string $storeSize): array
    {
        return [
            'health' => $health,
            'status' => 'open',
            'index' => $index,
            'uuid' => 'x',
            'pri' => '1',
            'rep' => '0',
            'docs.count' => $docsCount,
            'docs.deleted' => '0',
            'store.size' => $storeSize,
            'pri.store.size' => $storeSize,
        ];
    }

    /**
     * @param array<mixed>|Exception $catResponse
     */
    private function createCheck(array|Exception $catResponse, string $indexPrefix = 'pimcore_'): IndexHealthCheck
    {
        $configService = $this->makeEmpty(SearchIndexConfigServiceInterface::class, [
            'getClientType' => 'openSearch',
            'getIndexPrefix' => $indexPrefix,
        ]);
        $client = $this->makeEmpty(SearchClientInterface::class, [
            'getAllIndices' => function (array $params) use ($catResponse): array {
                $this->catCalls[] = $params;
                if ($catResponse instanceof Exception) {
                    throw $catResponse;
                }

                return $catResponse;
            },
        ]);

        return new IndexHealthCheck(
            client: $client,
            searchIndexConfigService: $configService,
            statusService: new IndexQueueStatusService(
                connection: $this->connection,
                searchIndexConfigService: $configService,
                clientName: 'default',
            ),
        );
    }
}
