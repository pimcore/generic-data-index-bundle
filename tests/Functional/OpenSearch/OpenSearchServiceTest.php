<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Functional\OpenSearch;

use OpenSearch\Client;
use OpenSearch\Common\Exceptions\Missing404Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\TermFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\OpenSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Tests\Support\Util\TestHelper;

class OpenSearchServiceTest extends \Codeception\Test\Unit
{
    /**
     * @var \Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester
     */
    protected $tester;

    protected function _after()
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    // tests

    public function testRefreshIndex(): void
    {
        /** @var OpenSearchService $openSearchService */
        $openSearchService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var Client $openSearchClient */
        $openSearchClient = $this->tester->grabService('generic-data-index.opensearch-client');

        $openSearchService->createIndex('testindex');
        $openSearchClient->create(['index' => 'testindex', 'refresh' => false, 'id'=>1, 'body' => ['test' => 'test']]);
        $openSearchService->refreshIndex('testindex');
        $this->assertEquals('test', $openSearchClient->get(['index' => 'testindex', 'id' => 1])['_source']['test']);
        $openSearchService->deleteIndex('testindex');
    }

    public function testDeleteIndex(): void
    {
        /** @var OpenSearchService $openSearchService */
        $openSearchService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var Client $openSearchClient */
        $openSearchClient = $this->tester->grabService('generic-data-index.opensearch-client');

        $openSearchService->createIndex('testindex');
        $this->assertTrue($openSearchClient->indices()->exists(['index' => 'testindex']));
        $openSearchService->deleteIndex('testindex');
        $this->assertFalse($openSearchClient->indices()->exists(['index' => 'testindex']));
    }

    public function testGetCurrentIndexVersion(): void
    {
        /** @var OpenSearchService $openSearchService */
        $openSearchService = $this->tester->grabService(SearchIndexServiceInterface::class);

        $openSearchService->createIndex('test_index-odd');
        $openSearchService->addAlias('test_index', 'test_index-odd');
        $this->assertEquals('odd', $openSearchService->getCurrentIndexVersion('test_index'));

        $openSearchService->deleteIndex('test_index-odd');

        $openSearchService->createIndex('test_index-even');
        $openSearchService->addAlias('test_index', 'test_index-even');
        $this->assertEquals('even', $openSearchService->getCurrentIndexVersion('test_index'));
        $openSearchService->deleteIndex('test_index-even');
    }

    public function testReindex(): void
    {
        /** @var OpenSearchService $openSearchService */
        $openSearchService = $this->tester->grabService(SearchIndexServiceInterface::class);

        $openSearchService->createIndex('test_index-odd', ['test'=> ['type'=>'object']]);
        $openSearchService->addAlias('test_index', 'test_index-odd');
        $openSearchService->reindex('test_index', ['test'=> ['type'=>'keyword']]);

        /** @var Client $openSearchClient */
        $openSearchClient = $this->tester->grabService('generic-data-index.opensearch-client');
        $mapping = $openSearchClient->indices()->getMapping(['index' => 'test_index']);
        $this->assertEquals('keyword', $mapping['test_index-even']['mappings']['properties']['test']['type']);

        $openSearchService->deleteIndex('test_index-even');
    }

    public function testCreateIndex(): void
    {
        /** @var OpenSearchService $openSearchService */
        $openSearchService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var Client $openSearchClient */
        $openSearchClient = $this->tester->grabService('generic-data-index.opensearch-client');

        $openSearchService->createIndex('test_index', ['test'=> ['type'=>'object']]);
        $mapping = $openSearchClient->indices()->getMapping(['index' => 'test_index']);
        $this->assertEquals('object', $mapping['test_index']['mappings']['properties']['test']['type']);
        $openSearchService->deleteIndex('test_index');
    }

    public function testAddAlias(): void
    {

        /** @var OpenSearchService $openSearchService */
        $openSearchService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var Client $openSearchClient */
        $openSearchClient = $this->tester->grabService('generic-data-index.opensearch-client');

        $openSearchService->createIndex('test_index');
        $openSearchService->createIndex('test_index2');
        $openSearchService->addAlias('test_index_alias', 'test_index');
        $openSearchService->addAlias('test_index_alias', 'test_index2');
        $this->assertTrue($openSearchClient->indices()->existsAlias(['name' => 'test_index_alias', 'index' => 'test_index']));
        $this->assertTrue($openSearchClient->indices()->existsAlias(['name' => 'test_index_alias', 'index' => 'test_index2']));
        $openSearchService->deleteIndex('test_index');
        $openSearchService->deleteIndex('test_index2');

    }

    public function testExistsAlias(): void
    {
        /** @var OpenSearchService $openSearchService */
        $openSearchService = $this->tester->grabService(SearchIndexServiceInterface::class);

        $openSearchService->createIndex('test_index');
        $openSearchService->addAlias('test_index_alias', 'test_index');

        $this->assertTrue($openSearchService->existsAlias('test_index_alias', 'test_index'));
        $this->assertFalse($openSearchService->existsAlias('test_index_alias', 'test_index2'));
        $this->assertFalse($openSearchService->existsAlias('test_index_alias2', 'test_index'));

        $openSearchService->deleteIndex('test_index');
    }

    public function testExistsIndex(): void
    {
        /** @var OpenSearchService $openSearchService */
        $openSearchService = $this->tester->grabService(SearchIndexServiceInterface::class);

        $openSearchService->createIndex('test_index');
        $this->assertTrue($openSearchService->existsIndex('test_index'));
        $this->assertFalse($openSearchService->existsIndex('test_index2'));
        $openSearchService->deleteIndex('test_index');
    }

    public function testDeleteAlias(): void
    {
        /** @var OpenSearchService $openSearchService */
        $openSearchService = $this->tester->grabService(SearchIndexServiceInterface::class);

        $openSearchService->createIndex('test_index');
        $openSearchService->addAlias('test_index_alias', 'test_index');
        $this->assertTrue($openSearchService->existsAlias('test_index_alias', 'test_index'));
        $openSearchService->deleteAlias('test_index', 'test_index_alias');
        $this->assertFalse($openSearchService->existsAlias('test_index_alias', 'test_index'));
        $openSearchService->deleteIndex('test_index');
    }

    public function testGetDocument(): void
    {
        /** @var OpenSearchService $openSearchService */
        $openSearchService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var Client $openSearchClient */
        $openSearchClient = $this->tester->grabService('generic-data-index.opensearch-client');

        $openSearchService->createIndex('test_index');
        $openSearchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>1, 'body' => ['test' => 'test']]);
        $document = $openSearchService->getDocument('test_index', 1);
        $this->assertEquals('test', $document['_source']['test']);
        $document = $openSearchService->getDocument('test_index', 2, true);
        $this->assertFalse($document['found']);
        $openSearchService->deleteIndex('test_index');
        $this->expectException(Missing404Exception::class);
        $openSearchService->getDocument('test_index', 2);
    }

    public function testPutMapping(): void
    {
        /** @var OpenSearchService $openSearchService */
        $openSearchService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var Client $openSearchClient */
        $openSearchClient = $this->tester->grabService('generic-data-index.opensearch-client');

        $openSearchService->createIndex('test_index');
        $openSearchService->putMapping([
            'index' => 'test_index',
            'body' => ['properties' => ['test' => ['type' => 'keyword']]],
        ]);

        $mapping = $openSearchClient->indices()->getMapping(['index' => 'test_index']);
        $this->assertEquals('keyword', $mapping['test_index']['mappings']['properties']['test']['type']);
        $openSearchService->deleteIndex('test_index');
    }

    public function testCountByAttributeValue(): void
    {
        /** @var OpenSearchService $openSearchService */
        $openSearchService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var Client $openSearchClient */
        $openSearchClient = $this->tester->grabService('generic-data-index.opensearch-client');

        $openSearchService->createIndex('test_index');
        $openSearchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>1, 'body' => ['test' => 'test']]);
        $openSearchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>2, 'body' => ['test' => 'test']]);
        $openSearchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>3, 'body' => ['test' => 'test']]);
        $openSearchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>4, 'body' => ['test' => 'test2']]);

        $this->assertEquals(3, $openSearchService->countByAttributeValue('test_index', 'test', 'test'));
        $this->assertEquals(1, $openSearchService->countByAttributeValue('test_index', 'test', 'test2'));
        $this->assertEquals(0, $openSearchService->countByAttributeValue('test_index', 'test', 'test3'));
        $openSearchService->deleteIndex('test_index');
    }

    public function testSearch(): void
    {
        /** @var OpenSearchService $openSearchService */
        $openSearchService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var Client $openSearchClient */
        $openSearchClient = $this->tester->grabService('generic-data-index.opensearch-client');

        $openSearchService->createIndex('test_index');
        $openSearchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>1, 'body' => ['test' => 'test']]);
        $openSearchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>2, 'body' => ['test' => 'test']]);
        $openSearchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>3, 'body' => ['test' => 'test']]);
        $openSearchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>4, 'body' => ['test' => 'test2']]);

        /** @var Search $search */
        $search = $openSearchService->createPaginatedSearch(1, 2);
        $search->addQuery(new TermFilter('test', 'test'));
        $this->assertEquals(2, $search->getSize());
        $this->assertEquals(0, $search->getFrom());

        $result = $openSearchService->search($search, 'test_index');
        $this->assertEquals(3, $result->getTotalHits());
        $this->assertCount(2, $result->getHits());

        $this->assertCount(1, $openSearchService->getExecutedSearches());
        $searchInformation = $openSearchService->getExecutedSearches()[0];
        $this->assertEquals($search, $searchInformation->getSearch());
        $this->assertTrue($searchInformation->isSuccess());
        $this->assertEquals($searchInformation->getResponse()['hits']['total']['value'], $result->getTotalHits());
        $this->assertIsNumeric($searchInformation->getExecutionTime());
        $this->assertNotEmpty($searchInformation->getStackTrace());

        $openSearchService->deleteIndex('test_index');
    }

    public function getTestGetStats(): void
    {
        /** @var OpenSearchService $openSearchService */
        $openSearchService = $this->tester->grabService(SearchIndexServiceInterface::class);
        $openSearchService->createIndex('test_index');
        $stats = $openSearchService->getStats('test_index');
        $this->assertArrayHasKey('indices', $stats);
        $this->assertArrayHasKey('test_index', $stats['indices']);
        $openSearchService->deleteIndex('test_index');
    }
}
