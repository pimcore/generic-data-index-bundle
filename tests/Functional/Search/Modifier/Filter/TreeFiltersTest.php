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

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\ExcludeFoldersFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\ParentIdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\PathFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Tests\Support\Util\TestHelper;

class TreeFiltersTest extends \Codeception\Test\Unit
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
    public function testParentIdFilter()
    {
        $asset = TestHelper::createImageAsset();
        $asset2 = TestHelper::createImageAsset();
        $asset3 = TestHelper::createImageAsset();
        $folder = TestHelper::createAssetFolder();

        $asset->setParent($folder)->save();
        $asset2->setParent($folder)->save();
        $asset3->setParent($folder)->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new ParentIdFilter($folder->getId()))
        ;
        $searchResult = $searchService->search($assetSearch);

        $this->assertIdArrayEquals([$asset->getId(), $asset2->getId(), $asset3->getId()], $searchResult->getIds());
    }

    public function testPathFilter(): void
    {
        $asset = TestHelper::createImageAsset();
        $asset2 = TestHelper::createImageAsset();
        $asset3 = TestHelper::createImageAsset();
        $folder = TestHelper::createAssetFolder();
        $folder2 = TestHelper::createAssetFolder();

        $folder2->setParent($folder)->save();

        $asset->setParent($folder)->save();
        $asset2->setParent($folder)->save();
        $asset3->setParent($folder2)->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new PathFilter($folder->getRealFullPath()))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertIdArrayEquals([$asset->getId(), $asset2->getId(), $asset3->getId(), $folder2->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new PathFilter($folder->getRealFullPath(), true))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertIdArrayEquals([$asset->getId(), $asset2->getId(), $folder2->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new PathFilter($folder->getRealFullPath(), true, true))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertIdArrayEquals([$asset->getId(), $asset2->getId(), $folder2->getId(), $folder->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new PathFilter($folder->getRealFullPath(), false, true))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertIdArrayEquals([$asset->getId(), $asset2->getId(), $asset3->getId(), $folder->getId(), $folder2->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new PathFilter($folder->getRealFullPath(), false, true))
            ->addModifier(new ExcludeFoldersFilter())
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertIdArrayEquals([$asset->getId(), $asset2->getId(), $asset3->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new PathFilter($asset->getRealFullPath(), false, true))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertIdArrayEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new PathFilter($asset->getRealFullPath()))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertIdArrayEquals([], $searchResult->getIds());
    }

    private function assertIdArrayEquals(array $ids1, array $ids2)
    {
        sort($ids1);
        sort($ids2);
        $this->assertEquals($ids1, $ids2);
    }
}
