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

namespace Functional\SearchIndex;

use OpenSearch\Common\Exceptions\Missing404Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Db;
use Pimcore\Tests\Support\Util\TestHelper;

class IndexQueueTest extends \Codeception\Test\Unit
{
    /**
     * @var \Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester
     */
    protected $tester;

    protected function _before()
    {
        $this->tester->disableSynchronousProcessing();
        $this->tester->clearQueue();
    }

    protected function _after()
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    // tests

    public function testIndexQueueRepository(): void
    {
        /**
         * @var IndexQueueRepository $indexQueueRepository
         */
        $indexQueueRepository = $this->tester->grabService(IndexQueueRepository::class);

        $entries = $indexQueueRepository->getUnhandledIndexQueueEntries();
        $entries = array_map(fn ($entry) => $indexQueueRepository->denormalizeDatabaseEntry($entry), $entries);
        $indexQueueRepository->deleteQueueEntries($entries);

        TestHelper::createImageAsset();

        $this->assertEquals(1, $indexQueueRepository->countIndexQueueEntries());
        $this->assertTrue($indexQueueRepository->dispatchableItemExists());

        $this->assertCount(1, $indexQueueRepository->getUnhandledIndexQueueEntries());
        // check if not dispatched
        $this->assertCount(1, $indexQueueRepository->getUnhandledIndexQueueEntries());

        $dispatchedItems = $indexQueueRepository->getUnhandledIndexQueueEntries(true);
        usleep(1000); //sleep for 1 ms to ensure that the dispatchId is different
        $this->assertEquals([], $indexQueueRepository->getUnhandledIndexQueueEntries(true));

        $dispatchedItems = array_map(fn ($entry) => $indexQueueRepository->denormalizeDatabaseEntry($entry), $dispatchedItems);

        $this->assertEquals(1, $indexQueueRepository->countIndexQueueEntries());
        $indexQueueRepository->deleteQueueEntries($dispatchedItems);
        $this->assertEquals(0, $indexQueueRepository->countIndexQueueEntries());

        $indexQueueRepository->enqueueBySelectQuery(
            $indexQueueRepository->generateSelectQuery('assets', [
                ElementType::ASSET->value,
                IndexName::ASSET->value,
                IndexQueueOperation::UPDATE->value,
                1234,
                0,
            ])
        );
        $this->assertEquals(
            Db::get()->fetchOne('select count(id) from assets'),
            $indexQueueRepository->countIndexQueueEntries()
        );
    }

    public function testAssetSaveNotEnqueued(): void
    {
        /**
         * @var SearchIndexConfigServiceInterface $searchIndexConfigService
         */
        $searchIndexConfigService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        $indexName = $searchIndexConfigService->getIndexName('asset');

        $asset = TestHelper::createImageAsset();

        $this->expectException(Missing404Exception::class);
        $this->tester->checkIndexEntry($asset->getId(), $indexName);
    }

    public function testAssetSaveProcessQueue(): void
    {
        /**
         * @var SearchIndexConfigServiceInterface $searchIndexConfigService
         */
        $searchIndexConfigService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        $indexName = $searchIndexConfigService->getIndexName('asset');

        $asset = TestHelper::createImageAsset();

        $this->assertNotEmpty(
            Db::get()->fetchOne(
                'select count(elementId) from generic_data_index_queue where elementId = ? and elementType="asset"',
                [$asset->getId()]
            )
        );

        $this->tester->runCommand('messenger:consume', ['--limit'=>2], ['pimcore_generic_data_index_queue']);
        $result = $this->tester->checkIndexEntry($asset->getId(), $indexName);
        $this->assertEquals($asset->getId(), $result['_source']['system_fields']['id']);
    }
}
