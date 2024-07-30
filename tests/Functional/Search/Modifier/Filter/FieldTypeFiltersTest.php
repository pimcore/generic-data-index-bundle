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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\Filter;

use Carbon\Carbon;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\DateFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\MultiSelectFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Model\DataObject\Unittest;
use Pimcore\Tests\Support\Util\TestHelper;

class FieldTypeFiltersTest extends \Codeception\Test\Unit
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
    public function testMultiSelectFilter()
    {
        /**
         * @var Unittest $object1
         * @var Unittest $object2
         * @var Unittest $object3
         * @var Unittest $object4
         */
        $object1 = TestHelper::createEmptyObject()->setKey('object1')->save();
        $object2 = TestHelper::createEmptyObject()->setKey('object2')->save();
        $object3 = TestHelper::createEmptyObject()->setKey('object3')->save();
        $object4 = TestHelper::createEmptyObject()->setKey('object4')->save();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $elementSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier(new MultiSelectFilter('key', ['object1', 'object2']))
        ;
        $searchResult = $searchService->search($elementSearch);
        $this->assertIdArrayEquals([$object1->getId(), $object2->getId()], $searchResult->getIds());

        $elementSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier(new MultiSelectFilter('system_fields.key', ['object3', 'object4'], false))
        ;
        $searchResult = $searchService->search($elementSearch);
        $this->assertIdArrayEquals([$object3->getId(), $object4->getId()], $searchResult->getIds());

        $elementSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier(new MultiSelectFilter('key', ['object3', 'object4'], false))
        ;
        $searchResult = $searchService->search($elementSearch);
        $this->assertIdArrayEquals([], $searchResult->getIds());
    }

    public function testDateFilterAsset()
    {
        $asset1 = TestHelper::createImageAsset()
            ->addMetadata('testDate', 'date', strtotime('2020-01-01'))
            ->save();

        $asset2 = TestHelper::createImageAsset()
            ->addMetadata('testDate', 'date', strtotime('2020-02-02 12:00:00'))
            ->save();

        $asset3 = TestHelper::createImageAsset()
            ->addMetadata('testDate', 'date', strtotime('2020-03-03'))
            ->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new DateFilter('testDate', Carbon::create('2019', 12, 31)));
        $searchResult = $searchService->search($assetSearch);
        $this->assertIdArrayEquals([$asset1->getId(), $asset2->getId(), $asset3->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new DateFilter('testDate', Carbon::create('2019', 12, 31), Carbon::create('2020', 1, 15)));
        $searchResult = $searchService->search($assetSearch);
        $this->assertIdArrayEquals([$asset1->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new DateFilter('testDate', null, null, Carbon::create('2020', 2, 2)));
        $searchResult = $searchService->search($assetSearch);
        $this->assertIdArrayEquals([$asset2->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new DateFilter('testDate', null, null, Carbon::create('2020', 2, 2), false));
        $searchResult = $searchService->search($assetSearch);
        $this->assertIdArrayEquals([], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new DateFilter('standard_fields.testDate.default', null, null, Carbon::create('2020', 2, 2), true, false));
        $searchResult = $searchService->search($assetSearch);
        $this->assertIdArrayEquals([$asset2->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new DateFilter('testDate', null, null, Carbon::create('2020', 2, 2), true, false));
        $searchResult = $searchService->search($assetSearch);
        $this->assertIdArrayEquals([], $searchResult->getIds());
    }
    public function testDateFilterDataObject()
    {
        $dataObject1 = TestHelper::createEmptyObject()
            ->setDate(Carbon::create('2020', 1, 1))
            ->save();

        $dataObject2 = TestHelper::createEmptyObject()
            ->setDate(Carbon::create('2020', 2, 2, 12))
            ->save();

        $dataObject3 = TestHelper::createEmptyObject()
            ->setDate(Carbon::create('2020', 3, 3))
            ->save();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $dataObjectSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier(new DateFilter('date', Carbon::create('2019', 12, 31)))
        ;
        $searchResult = $searchService->search($dataObjectSearch);
        $this->assertIdArrayEquals([$dataObject1->getId(), $dataObject2->getId(), $dataObject3->getId()], $searchResult->getIds());

        $dataObjectSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new DateFilter('date', Carbon::create('2019', 12, 31), Carbon::create('2020', 1, 15)))
        ;
        $searchResult = $searchService->search($dataObjectSearch);
        $this->assertIdArrayEquals([$dataObject1->getId()], $searchResult->getIds());

        $dataObjectSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new DateFilter('date', null, null, Carbon::create('2020', 2, 2)))
        ;
        $searchResult = $searchService->search($dataObjectSearch);
        $this->assertIdArrayEquals([$dataObject2->getId()], $searchResult->getIds());

        $dataObjectSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new DateFilter('date', null, null, Carbon::create('2020', 2, 2), false))
        ;
        $searchResult = $searchService->search($dataObjectSearch);
        $this->assertIdArrayEquals([], $searchResult->getIds());

        $dataObjectSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new DateFilter('standard_fields.date', null, null, Carbon::create('2020', 2, 2), true, false))
        ;
        $searchResult = $searchService->search($dataObjectSearch);
        $this->assertIdArrayEquals([$dataObject2->getId()], $searchResult->getIds());

        $dataObjectSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new DateFilter('date', null, null, Carbon::create('2020', 2, 2), true, false))
        ;
        $searchResult = $searchService->search($dataObjectSearch);
        $this->assertIdArrayEquals([], $searchResult->getIds());

    }

    private function assertIdArrayEquals(array $ids1, array $ids2)
    {
        sort($ids1);
        sort($ids2);
        $this->assertEquals($ids1, $ids2);
    }
}
