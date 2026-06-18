<?php

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Functional\SearchIndex;

use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Db;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Tests\Support\Util\TestHelper;

class IndexQueueTest extends Unit
{
    protected IndexTester $tester;

    private SearchIndexConfigServiceInterface $searchIndexConfigService;

    private const ASSET_INDEX_NAME = 'asset';

    private const DOCUMENT_INDEX_NAME = 'document';

    private const IMAGE_KEY = 'image';

    protected function _before()
    {
        $this->searchIndexConfigService = $this->tester->grabService(
            SearchIndexConfigServiceInterface::class
        );
        $this->tester->disableSynchronousProcessing();
        $this->tester->enableSynchronousProcessingRelatedIds();
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
                '1234',
                '0',
            ])
        );
        $this->assertEquals(
            Db::get()->fetchOne('select count(id) from assets'),
            $indexQueueRepository->countIndexQueueEntries()
        );
    }

    public function testAssetSaveNotEnqueued(): void
    {
        $indexName = $this->searchIndexConfigService->getIndexName(self::ASSET_INDEX_NAME);

        $asset = TestHelper::createImageAsset();
        $this->tester->checkDeletedIndexEntry($asset->getId(), $indexName);
    }

    public function testAssetSaveProcessQueue(): void
    {
        /**
         * @var SearchIndexConfigServiceInterface $searchIndexConfigService
         */
        $searchIndexConfigService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        $indexName = $searchIndexConfigService->getIndexName(self::ASSET_INDEX_NAME);

        $asset = TestHelper::createImageAsset();

        $this->assertGreaterThan(
            0,
            Db::get()->fetchOne(
                'select count(elementId) from generic_data_index_queue where elementId = ? and elementType="asset"',
                [$asset->getId()]
            )
        );

        $this->tester->consume();
        $result = $this->tester->checkIndexEntry($asset->getId(), $indexName);
        $this->assertEquals($asset->getId(), $result['_source']['system_fields']['id']);
    }

    /**
     * @throws Exception
     */
    public function testAssetDeleteWithQueue(): void
    {
        $asset = TestHelper::createImageAsset();
        $assetIndex = $this->searchIndexConfigService->getIndexName(self::ASSET_INDEX_NAME);
        $this->tester->consume();

        $this->checkAndDeleteElement($asset, $assetIndex);
        $this->tester->consume();

        $this->tester->checkDeletedIndexEntry($asset->getId(), $assetIndex);
    }

    /**
     * @throws Exception
     */
    public function testDocumentDeleteWithQueue(): void
    {
        $document = TestHelper::createEmptyDocument();
        $documentIndex = $this->searchIndexConfigService->getIndexName(self::DOCUMENT_INDEX_NAME);
        $this->tester->consume();

        $this->checkAndDeleteElement($document, $documentIndex);
        $this->tester->consume();

        $this->tester->checkDeletedIndexEntry($document->getId(), $documentIndex);
    }

    /**
     * @throws Exception
     */
    public function testDataObjectDeleteWithQueue(): void
    {
        $object = TestHelper::createEmptyObject();
        $objectIndex = $this->searchIndexConfigService->getIndexName($object->getClassName(), true);
        $this->tester->consume();

        $this->checkAndDeleteElement($object, $objectIndex);
        $this->tester->consume();

        $this->tester->checkDeletedIndexEntry($object->getId(), $objectIndex);
    }

    /**
     * @throws Exception
     */
    public function testDependenciesWithQueue(): void
    {
        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);
        $dataObjectSearch = $searchProvider->createDataObjectSearch();

        $asset = TestHelper::createImageAsset();
        $object = TestHelper::createEmptyObject();
        $object->setImage($asset);
        $object->save();

        $assetIndex = $this->searchIndexConfigService->getIndexName(self::ASSET_INDEX_NAME);
        $objectIndex = $this->searchIndexConfigService->getIndexName($object->getClassName(), true);

        $this->checkQueueEntry($asset->getId(), ElementType::ASSET->value);
        $this->checkQueueEntry($object->getId(), ElementType::DATA_OBJECT->value);
        $this->tester->consume();

        $this->tester->checkIndexEntry($object->getId(), $objectIndex);
        $this->assertNotNull($this->getImageValueFromIndex($searchService, $dataObjectSearch));
        $this->checkAndDeleteElement($asset, $assetIndex);

        // asset is deleted, so the object should be updated as it has a dependency to asset
        $this->checkQueueEntry($object->getId(), ElementType::DATA_OBJECT->value);
        $this->tester->consume();

        $this->assertNull($this->getImageValueFromIndex($searchService, $dataObjectSearch));
    }

    /**
     * Regression test: when a queue entry exists with a full ES index name (e.g. stored by enqueueByItemList
     * from HitData), a subsequent enqueue for the same element must overwrite elementIndexName so the
     * DELETE handler receives the short class name and not the already-prefixed full index name.
     *
     * Without the fix in updateFromChunk, the operation field was updated to 'delete' but
     * elementIndexName stayed as 'pimcore_data-object_card-odd', causing getIndexName() to
     * double-prefix it into 'pimcore_data-object_pimcore_data-object_card-odd'.
     */
    public function testEnqueueBySelectQueryUpdatesElementIndexName(): void
    {
        /** @var IndexQueueRepository $repo */
        $repo = $this->tester->grabService(IndexQueueRepository::class);

        $object = TestHelper::createEmptyObject();
        $elementId = $object->getId();
        $elementType = ElementType::DATA_OBJECT->value;
        $shortIndexName = $object->getClassName();
        $fullIndexName = $this->searchIndexConfigService->getIndexName($shortIndexName, true) . '-odd';
        // createEmptyObject() enqueues the element automatically; overwrite elementIndexName with the
        // stale full ES index name to reproduce the state set by enqueueByItemList (via HitData::getIndex()).
        Db::get()->executeStatement(
            'UPDATE generic_data_index_queue SET elementIndexName = ? WHERE elementId = ? AND elementType = ?',
            [$fullIndexName, $elementId, $elementType]
        );

        $this->assertEquals(
            $fullIndexName,
            Db::get()->fetchOne(
                'SELECT elementIndexName FROM generic_data_index_queue WHERE elementId = ? AND elementType = ?',
                [$elementId, $elementType]
            )
        );

        $repo->enqueueBySelectQuery(
            $repo->generateSelectQuery(
                'objects',
                [
                    $elementType,
                    $shortIndexName,
                    IndexQueueOperation::DELETE->value,
                    (string)(time() * 1000 + 1),
                    '0',
                ],
                'id',
                ['id' => $elementId],
                ['id']
            )
        );

        $this->assertEquals(
            $shortIndexName,
            Db::get()->fetchOne(
                'SELECT elementIndexName FROM generic_data_index_queue WHERE elementId = ? AND elementType = ?',
                [$elementId, $elementType]
            ),
            'elementIndexName must be overwritten when an existing queue entry is updated via the INSERT IGNORE + UPDATE fallback path'
        );
    }

    private function checkAndDeleteElement(ElementInterface $element, string $indexName): void
    {
        $this->tester->checkIndexEntry($element->getId(), $indexName);
        $element->delete();
    }

    private function checkQueueEntry(string $elementId, string $elementType): void
    {
        $this->assertGreaterThan(
            0,
            Db::get()->fetchOne(
                'select count(elementId) from generic_data_index_queue where elementId = ? and elementType=?',
                [$elementId, $elementType]
            )
        );
    }

    private function getImageValueFromIndex(
        DataObjectSearchServiceInterface $searchService,
        DataObjectSearchInterface $dataObjectSearch
    ): ?array {
        $searchResult = $searchService->search($dataObjectSearch);
        $this->assertCount(1, $searchResult->getItems());
        $data = $searchResult->getItems()[0]->getSearchIndexData();

        return $data[FieldCategory::STANDARD_FIELDS->value][self::IMAGE_KEY];
    }
}
