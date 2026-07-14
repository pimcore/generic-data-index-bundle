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

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\BooleanFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\ExcludeFoldersFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\ExcludeVariantsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IntegerFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\NumberFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Tests\Support\Util\TestHelper;

class BasicFiltersTest extends \Codeception\Test\Unit
{
    /**
     * @var \Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester
     */
    protected $tester;

    protected function _before()
    {
        $this->tester->enableSynchronousProcessing();
    }

    protected function _after()
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    // tests
    public function testExcludeFolders()
    {
        $asset = TestHelper::createImageAsset();

        $folder = TestHelper::createAssetFolder();
        $asset->setParent($folder)->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(2, $searchResult->getItems());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new ExcludeFoldersFilter())
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(1, $searchResult->getItems());
        $this->assertNotEquals('folder', $searchResult->getItems()[0]->getType());
    }

    // tests
    public function testExcludeVariants()
    {
        $object = TestHelper::createEmptyObject(save: false);
        $object->setType(AbstractObject::OBJECT_TYPE_VARIANT);
        $object->save();

        TestHelper::createEmptyObject();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService(DataObjectSearchServiceInterface::class);
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $search = $searchProvider->createDataObjectSearch();
        $searchResult = $searchService->search($search);
        $this->assertCount(2, $searchResult->getItems());

        $search = $searchProvider->createDataObjectSearch();
        $search->addModifier(new ExcludeVariantsFilter());
        $searchResult = $searchService->search($search);
        $this->assertCount(1, $searchResult->getItems());
        $this->assertNotEquals(AbstractObject::OBJECT_TYPE_VARIANT, $searchResult->getItems()[0]->getType());
    }

    public function testIdFilter()
    {
        $asset = TestHelper::createImageAsset();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new IdFilter($asset->getId()))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(1, $searchResult->getItems());
        $this->assertEquals($asset->getId(), $searchResult->getItems()[0]->getId());

        // use an id above the freshly created one — a literal id (e.g. 123) collides
        // with auto-increment element ids once enough elements were created before
        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new IdFilter($asset->getId() + 1000))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(0, $searchResult->getItems());
    }

    public function testIdsFilter()
    {
        $asset = TestHelper::createImageAsset();
        $asset2 = TestHelper::createImageAsset();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new IdsFilter([$asset->getId()]))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(1, $searchResult->getItems());
        $this->assertEquals($asset->getId(), $searchResult->getItems()[0]->getId());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new IdsFilter([$asset->getId(), $asset2->getId()]))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(2, $searchResult->getItems());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new IdsFilter([$asset2->getId() + 1000]))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(0, $searchResult->getItems());
    }

    public function testIntegerFilter()
    {
        $asset = TestHelper::createImageAsset();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new IntegerFilter('userOwner', $asset->getUserOwner()))
            ->addModifier(new IntegerFilter('userModification', $asset->getUserModification()))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertCount(1, $searchResult->getItems());
        $this->assertEquals($asset->getId(), $searchResult->getItems()[0]->getId());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new IntegerFilter('userOwner', $asset->getUserOwner(), false))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEmpty($searchResult->getItems());
    }

    public function testNumberFilter()
    {
        $number = 124;
        $object = $this->tester->createFullyFledgedObjectUnittest();
        $object2 = $this->tester->createFullyFledgedObjectUnittest();
        $object2->setNumber(420)->save();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService(DataObjectSearchServiceInterface::class);
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $search = $searchProvider->createDataObjectSearch();
        $search->addModifier(new NumberFilter('number', $number));
        $searchResult = $searchService->search($search);
        $this->assertCount(1, $searchResult->getItems());
        $this->assertEquals($object->getId(), $searchResult->getItems()[0]->getId());

        $search = $searchProvider->createDataObjectSearch();
        $search->addModifier(new NumberFilter('number', $number, false));
        $searchResult = $searchService->search($search);
        $this->assertEmpty($searchResult->getItems());
    }

    public function testBooleanFilter()
    {
        $objects = TestHelper::createEmptyObjects(count: 3);
        $objects[1]->setPublished(false)->save();
        $objects[2]->setPublished(false)->save();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService(DataObjectSearchServiceInterface::class);
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $search = $searchProvider
            ->createDataObjectSearch()
            ->addModifier(new BooleanFilter('system_fields.published', true))
        ;
        $searchResult = $searchService->search($search);
        $this->assertCount(1, $searchResult->getItems());
        $this->assertEquals($objects[0]->getId(), $searchResult->getItems()[0]->getId());

        $search = $searchProvider
            ->createDataObjectSearch()
            ->addModifier(new BooleanFilter('system_fields.published', false))
        ;
        $searchResult = $searchService->search($search);
        $this->assertCount(2, $searchResult->getItems());
    }
}
