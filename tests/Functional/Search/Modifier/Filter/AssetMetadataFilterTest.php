<?php

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\Filter;

use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\DateFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Asset\AssetMetaDataFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Tests\Support\Util\TestHelper;

class AssetMetadataFilterTest extends \Codeception\Test\Unit
{
    /**
     * @var \Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester
     */
    protected $tester;

    protected function _before()
    {
        $this->tester->enableSynchronousProcessing();
        date_default_timezone_set('UTC');
    }

    protected function _after()
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    // tests
    public function testAssetMetaDataFilterInput()
    {
        $asset = TestHelper::createImageAsset();
        $asset->addMetadata('testInput', 'input', 'lorem ipsum', 'en');
        $asset->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testInput', 'input', 'lorem ipsum', 'en'))

        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testInput', 'input', 'lorem*', 'en'))

        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testInput', 'input', '*ipsum', 'en'))

        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testInput', 'input', 'lor*psum', 'en'))

        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testInput', 'input', 'ipsum*', 'en'))

        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([], $searchResult->getIds());
    }

    public function testAssetMetaDataFilterTextarea()
    {
        $asset = TestHelper::createImageAsset();
        $asset->addMetadata('testTextarea', 'textarea', 'lorem ipsum', 'en');
        $asset->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testTextarea', 'textarea', 'lorem ipsum', 'en'))

        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testTextarea', 'textarea', 'lorem*', 'en'))

        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testTextarea', 'textarea', '*ipsum', 'en'))

        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testTextarea', 'textarea', 'lor*psum', 'en'))

        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testTextarea', 'textarea', 'ipsum*', 'en'))

        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([], $searchResult->getIds());
    }

    public function testAssetMetaDataFilterSelect()
    {
        $asset = TestHelper::createImageAsset();
        $asset->addMetadata('testSelect', 'select', 'value1');
        $asset->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testSelect', 'select', 'value1'))

        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testSelect', 'select', 'value2'))

        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([], $searchResult->getIds());

        $asset->addMetadata('testSelect', 'select', 'value2');
        $asset->save();

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testSelect', 'select', 'value2'))

        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testSelect', 'select', ['value2', 'value3']))

        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());
    }

    public function testAssetMetaDataFilterAssetRelation()
    {
        $asset = TestHelper::createImageAsset();
        $asset->addMetadata('testAsset', 'asset', $asset);
        $asset->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testAsset', 'asset', $asset->getId()))
        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testAsset', 'asset', [$asset->getId(), 123]))
        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testAsset', 'asset', [123]))
        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([], $searchResult->getIds());
    }

    public function testAssetMetaDataFilterDocumentRelation()
    {
        $asset = TestHelper::createImageAsset();
        $document = TestHelper::createEmptyDocument();

        $asset->addMetadata('testDocument', 'document', $document);
        $asset->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testDocument', 'document', $document->getId()))
        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testDocument', 'document', [$document->getId(), 123]))
        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testDocument', 'document', [123]))
        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([], $searchResult->getIds());
    }

    public function testAssetMetaDataFilterObjectRelation()
    {
        $asset = TestHelper::createImageAsset();
        $object = TestHelper::createObjectFolder();

        $asset->addMetadata('testObject', 'object', $object);
        $asset->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testObject', 'object', $object->getId()))
        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testObject', 'object', [$object->getId(), 123]))
        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testObject', 'object', [123]))
        ;

        $searchResult = $searchService->search($assetSearch);

        $this->assertEquals([], $searchResult->getIds());
    }

    public function testAssetMetaDataFilterCheckbox()
    {
        $asset = TestHelper::createImageAsset();

        $asset->addMetadata('testCheckbox', 'checkbox', true);
        $asset->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testCheckbox', 'checkbox', true))
        ;

        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $asset->addMetadata('testCheckbox', 'checkbox', false);
        $asset->save();

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testCheckbox', 'checkbox', false))
        ;

        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());
        $asset->save();

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testCheckbox', 'checkbox', true))
        ;

        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([], $searchResult->getIds());
        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testCheckbox', 'checkbox', [true, false]))
        ;

        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());
    }

    public function testAssetMetaDataFilterDate()
    {
        $asset = TestHelper::createImageAsset();

        $asset->addMetadata('testDate', 'date', strtotime('2020-01-01'));
        $asset->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testDate', 'date', [DateFilter::PARAM_ON => strtotime('2020-01-01')]))
        ;

        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testDate', 'date', [DateFilter::PARAM_START => strtotime('2019-01-01')]))
        ;

        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testDate', 'date', [DateFilter::PARAM_END => strtotime('2020-01-01')]))
        ;

        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testDate', 'date', [DateFilter::PARAM_END => strtotime('2019-01-01')]))
        ;

        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new AssetMetaDataFilter('testDate', 'date', [DateFilter::PARAM_START => strtotime('2021-01-01')]))
        ;

        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([], $searchResult->getIds());

    }
}
