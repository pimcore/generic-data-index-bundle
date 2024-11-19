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

namespace Functional\DefaultSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\SearchClient\SearchClientInterface;
use Pimcore\Tests\Support\Util\TestHelper;

class DefaultSearchServiceTest extends \Codeception\Test\Unit
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
        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var SearchClientInterface $searchClient */
        $searchClient = $this->tester->grabService('generic-data-index.search-client');

        $searchIndexService->createIndex('testindex');
        $searchClient->create(['index' => 'testindex', 'refresh' => false, 'id'=>1, 'body' => ['test' => 'test']]);
        $searchIndexService->refreshIndex('testindex');
        $this->assertEquals('test', $searchClient->get(['index' => 'testindex', 'id' => 1])['_source']['test']);
        $searchIndexService->deleteIndex('testindex');
    }

    public function testDeleteIndex(): void
    {
        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var SearchClientInterface $searchClient */
        $searchClient = $this->tester->grabService('generic-data-index.search-client');

        $searchIndexService->createIndex('testindex');
        $this->assertTrue($searchClient->existsIndex(['index' => 'testindex']));
        $searchIndexService->deleteIndex('testindex');
        $this->assertFalse($searchClient->existsIndex(['index' => 'testindex']));
    }

    public function testGetCurrentIndexVersion(): void
    {
        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);

        $searchIndexService->createIndex('test_index-odd');
        $searchIndexService->addAlias('test_index', 'test_index-odd');
        $this->assertEquals('odd', $searchIndexService->getCurrentIndexVersion('test_index'));

        $searchIndexService->deleteIndex('test_index-odd');

        $searchIndexService->createIndex('test_index-even');
        $searchIndexService->addAlias('test_index', 'test_index-even');
        $this->assertEquals('even', $searchIndexService->getCurrentIndexVersion('test_index'));
        $searchIndexService->deleteIndex('test_index-even');
    }

    public function testReindex(): void
    {
        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);

        $searchIndexService->createIndex('test_index-odd', ['test'=> ['type'=>'object']]);
        $searchIndexService->addAlias('test_index', 'test_index-odd');
        $searchIndexService->reindex('test_index', ['test'=> ['type'=>'keyword']]);

        /** @var SearchClientInterface $searchClient */
        $searchClient = $this->tester->grabService('generic-data-index.search-client');
        $mapping = $searchClient->getIndexMapping(['index' => 'test_index']);
        $this->assertEquals('keyword', $mapping['test_index-even']['mappings']['properties']['test']['type']);

        $searchIndexService->deleteIndex('test_index-even');
    }

    public function testCreateIndex(): void
    {
        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var SearchClientInterface $searchClient */
        $searchClient = $this->tester->grabService('generic-data-index.search-client');

        $searchIndexService->createIndex('test_index', ['test'=> ['type'=>'object']]);
        $mapping = $searchClient->getIndexMapping(['index' => 'test_index']);
        $this->assertEquals('object', $mapping['test_index']['mappings']['properties']['test']['type']);
        $searchIndexService->deleteIndex('test_index');
    }

    public function testAddAlias(): void
    {

        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var SearchClientInterface $searchClient */
        $searchClient = $this->tester->grabService('generic-data-index.search-client');

        $searchIndexService->createIndex('test_index');
        $searchIndexService->createIndex('test_index2');
        $searchIndexService->addAlias('test_index_alias', 'test_index');
        $searchIndexService->addAlias('test_index_alias', 'test_index2');
        $this->assertTrue($searchClient->existsIndexAlias(['name' => 'test_index_alias', 'index' => 'test_index']));
        $this->assertTrue($searchClient->existsIndexAlias(['name' => 'test_index_alias', 'index' => 'test_index2']));
        $searchIndexService->deleteIndex('test_index');
        $searchIndexService->deleteIndex('test_index2');

    }

    public function testExistsAlias(): void
    {
        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);

        $searchIndexService->createIndex('test_index');
        $searchIndexService->addAlias('test_index_alias', 'test_index');

        $this->assertTrue($searchIndexService->existsAlias('test_index_alias', 'test_index'));
        $this->assertFalse($searchIndexService->existsAlias('test_index_alias', 'test_index2'));
        $this->assertFalse($searchIndexService->existsAlias('test_index_alias2', 'test_index'));

        $searchIndexService->deleteIndex('test_index');
    }

    public function testExistsIndex(): void
    {
        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);

        $searchIndexService->createIndex('test_index');
        $this->assertTrue($searchIndexService->existsIndex('test_index'));
        $searchIndexService->deleteIndex('test_index2');
        $this->assertFalse($searchIndexService->existsIndex('test_index2'));
        $searchIndexService->deleteIndex('test_index');
    }

    public function testDeleteAlias(): void
    {
        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);

        $searchIndexService->createIndex('test_index');
        $searchIndexService->addAlias('test_index_alias', 'test_index');
        $this->assertTrue($searchIndexService->existsAlias('test_index_alias', 'test_index'));
        $searchIndexService->deleteAlias('test_index', 'test_index_alias');
        $this->assertFalse($searchIndexService->existsAlias('test_index_alias', 'test_index'));
        $searchIndexService->deleteIndex('test_index');
    }

    public function testGetDocument(): void
    {
        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var SearchClientInterface $searchClient */
        $searchClient = $this->tester->grabService('generic-data-index.search-client');

        $searchIndexService->createIndex('test_index');
        $searchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>1, 'body' => ['test' => 'test']]);
        $document = $searchIndexService->getDocument('test_index', 1);
        $this->assertEquals('test', $document['_source']['test']);

        $searchIndexService->getDocument('test_index', 2, true);
        $searchIndexService->deleteIndex('test_index');
        $this->tester->checkDeletedIndexEntry(1, 'test_index');
    }

    public function testPutMapping(): void
    {
        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var SearchClientInterface $searchClient */
        $searchClient = $this->tester->grabService('generic-data-index.search-client');

        $searchIndexService->createIndex('test_index');
        $searchIndexService->putMapping([
            'index' => 'test_index',
            'body' => ['properties' => ['test' => ['type' => 'keyword']]],
        ]);

        $mapping = $searchClient->getIndexMapping(['index' => 'test_index']);
        $this->assertEquals('keyword', $mapping['test_index']['mappings']['properties']['test']['type']);
        $searchIndexService->deleteIndex('test_index');
    }

    public function testCountByAttributeValue(): void
    {
        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var SearchClientInterface $searchClient */
        $searchClient = $this->tester->grabService('generic-data-index.search-client');

        $searchIndexService->createIndex('test_index');
        $searchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>1, 'body' => ['test' => 'test']]);
        $searchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>2, 'body' => ['test' => 'test']]);
        $searchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>3, 'body' => ['test' => 'test']]);
        $searchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>4, 'body' => ['test' => 'test2']]);

        $this->assertEquals(3, $searchIndexService->countByAttributeValue('test_index', 'test', 'test'));
        $this->assertEquals(1, $searchIndexService->countByAttributeValue('test_index', 'test', 'test2'));
        $this->assertEquals(0, $searchIndexService->countByAttributeValue('test_index', 'test', 'test3'));
        $searchIndexService->deleteIndex('test_index');
    }

    public function testSearch(): void
    {
        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);
        /** @var SearchClientInterface $searchClient */
        $searchClient = $this->tester->grabService('generic-data-index.search-client');

        $searchIndexService->createIndex('test_index');
        $searchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>1, 'body' => ['test' => 'test']]);
        $searchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>2, 'body' => ['test' => 'test']]);
        $searchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>3, 'body' => ['test' => 'test']]);
        $searchClient->create(['index' => 'test_index', 'refresh' => true, 'id'=>4, 'body' => ['test' => 'test2']]);

        /** @var Search $search */
        $search = $searchIndexService->createPaginatedSearch(1, 2);
        $search->addQuery(new TermFilter('test', 'test'));
        $this->assertEquals(2, $search->getSize());
        $this->assertEquals(0, $search->getFrom());

        $result = $searchIndexService->search($search, 'test_index');
        $this->assertEquals(3, $result->getTotalHits());
        $this->assertCount(2, $result->getHits());

        $this->assertCount(1, $searchIndexService->getExecutedSearches());
        $searchInformation = $searchIndexService->getExecutedSearches()[0];
        $this->assertEquals($search, $searchInformation->getSearch());
        $this->assertTrue($searchInformation->isSuccess());
        $this->assertEquals($searchInformation->getResponse()['hits']['total']['value'], $result->getTotalHits());
        $this->assertIsNumeric($searchInformation->getExecutionTime());
        $this->assertNotEmpty($searchInformation->getStackTrace());

        $searchIndexService->deleteIndex('test_index');
    }

    public function getTestGetStats(): void
    {
        /** @var SearchIndexServiceInterface $searchIndexService */
        $searchIndexService = $this->tester->grabService(SearchIndexServiceInterface::class);
        $searchIndexService->createIndex('test_index');
        $stats = $searchIndexService->getStats('test_index');
        $this->assertArrayHasKey('indices', $stats);
        $this->assertArrayHasKey('test_index', $stats['indices']);
        $searchIndexService->deleteIndex('test_index');
    }
}
