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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\Filter;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Dependency\UnreferencedFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Tests\Support\Util\TestHelper;

final class UnreferencedFilterTest extends Unit
{
    protected IndexTester $tester;

    protected function _before(): void
    {
        $this->tester->enableSynchronousProcessing();
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    public function testUnreferencedAssetIsIndexedAsFalse(): void
    {
        $asset = TestHelper::createImageAsset();

        $response = $this->getIndexEntry($asset);
        $this->assertFalse($response['_source']['system_fields']['isReferenced']);
    }

    public function testReferencedAssetIsIndexedAsTrue(): void
    {
        $asset = TestHelper::createImageAsset();
        $this->createObjectReferencingAsset($asset);

        $response = $this->getIndexEntry($asset);
        $this->assertTrue($response['_source']['system_fields']['isReferenced']);
    }

    public function testUnreferencedFilterReturnsOnlyUnreferencedAssets(): void
    {
        $asset1 = TestHelper::createImageAsset();
        $asset2 = TestHelper::createImageAsset();
        $asset3 = TestHelper::createImageAsset();

        $this->createObjectReferencingAsset($asset1);
        $this->createObjectReferencingAsset($asset2);

        $searchResult = $this->searchWithUnreferencedFilter();

        $this->assertEquals([$asset3->getId()], $searchResult->getIds());
    }

    public function testIsReferencedUpdatedWhenReferenceRemoved(): void
    {
        $asset = TestHelper::createImageAsset();
        $object = $this->createObjectReferencingAsset($asset);

        $response = $this->getIndexEntry($asset);
        $this->assertTrue($response['_source']['system_fields']['isReferenced']);

        $object->setImage(null);
        $object->save();

        $response = $this->getIndexEntry($asset);
        $this->assertFalse($response['_source']['system_fields']['isReferenced']);

        $searchResult = $this->searchWithUnreferencedFilter();
        $this->assertContains($asset->getId(), $searchResult->getIds());
    }

    public function testIsReferencedUpdatedWhenReferenceAdded(): void
    {
        $asset = TestHelper::createImageAsset();

        $response = $this->getIndexEntry($asset);
        $this->assertFalse($response['_source']['system_fields']['isReferenced']);

        $this->createObjectReferencingAsset($asset);

        $response = $this->getIndexEntry($asset);
        $this->assertTrue($response['_source']['system_fields']['isReferenced']);

        $searchResult = $this->searchWithUnreferencedFilter();
        $this->assertNotContains($asset->getId(), $searchResult->getIds());
    }

    public function testAssetStaysReferencedWhenOneOfMultipleReferencesRemoved(): void
    {
        $asset = TestHelper::createImageAsset();
        $object1 = $this->createObjectReferencingAsset($asset);
        $this->createObjectReferencingAsset($asset);

        $response = $this->getIndexEntry($asset);
        $this->assertTrue($response['_source']['system_fields']['isReferenced']);

        $object1->setImage(null);
        $object1->save();

        $response = $this->getIndexEntry($asset);
        $this->assertTrue($response['_source']['system_fields']['isReferenced']);
    }

    public function testAssetBecomesUnreferencedWhenAllReferencesRemoved(): void
    {
        $asset = TestHelper::createImageAsset();
        $object1 = $this->createObjectReferencingAsset($asset);
        $object2 = $this->createObjectReferencingAsset($asset);

        $object1->setImage(null);
        $object1->save();

        $object2->setImage(null);
        $object2->save();

        $response = $this->getIndexEntry($asset);
        $this->assertFalse($response['_source']['system_fields']['isReferenced']);
    }

    public function testUnreferencedFilterReturnsEmptyWhenAllAssetsReferenced(): void
    {
        $asset1 = TestHelper::createImageAsset();
        $asset2 = TestHelper::createImageAsset();

        $this->createObjectReferencingAsset($asset1);
        $this->createObjectReferencingAsset($asset2);

        $searchResult = $this->searchWithUnreferencedFilter();
        $this->assertEmpty($searchResult->getIds());
    }

    private function createObjectReferencingAsset(Asset $asset): Concrete
    {
        return TestHelper::createEmptyObject()
            ->setImage($asset)
            ->save();
    }

    private function getIndexEntry(Asset $asset): array
    {
        /** @var SearchIndexConfigServiceInterface $configService */
        $configService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        $indexName = $configService->getIndexName('asset');

        return $this->tester->checkIndexEntry($asset->getId(), $indexName);
    }

    private function searchWithUnreferencedFilter(): \Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResult
    {
        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new UnreferencedFilter());

        return $searchService->search($assetSearch);
    }
}
