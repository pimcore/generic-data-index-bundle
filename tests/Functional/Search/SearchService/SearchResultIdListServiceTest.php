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

namespace Functional\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Search\SortDirection;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\DocumentSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchResultIdListServiceInterface;
use Pimcore\Tests\Support\Util\TestHelper;

final class SearchResultIdListServiceTest extends \Codeception\Test\Unit
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

    public function testSearchAssetIdList(): void
    {
        $asset = TestHelper::createImageAsset()->setKey('asset1')->save();
        $asset2 = TestHelper::createImageAsset()->setKey('asset2')->save();
        $asset3 = TestHelper::createImageAsset()->setKey('asset3')->save();

        /** @var SearchResultIdListServiceInterface $searchResultIdListService */
        $searchResultIdListService = $this->tester->grabService(SearchResultIdListServiceInterface::class);

        $assetIds = $searchResultIdListService->getAllIds(
            new AssetSearch()
        );

        $this->assertIdArrayEquals([$asset->getId(), $asset2->getId(), $asset3->getId()], $assetIds);

        $assetIds = $searchResultIdListService->getIdsForCurrentPage(
            (new AssetSearch())
                ->addModifier(new OrderByFullPath(SortDirection::DESC))
                ->setPage(1)
                ->setPageSize(2)
        );

        $this->assertEquals([$asset3->getId(), $asset2->getId()], $assetIds);
    }

    public function testSearchDataObjectIdList(): void
    {
        $object1 = TestHelper::createEmptyObject()->setKey('object1')->save();
        $object2 = TestHelper::createEmptyObject()->setKey('object2')->save();
        $object3 = TestHelper::createEmptyObject()->setKey('object3')->save();

        /** @var SearchResultIdListServiceInterface $searchResultIdListService */
        $searchResultIdListService = $this->tester->grabService(SearchResultIdListServiceInterface::class);

        $objectIds = $searchResultIdListService->getAllIds(
            (new DataObjectSearch())
                ->setClassDefinition($object1->getClass())
        );

        $this->assertIdArrayEquals([$object1->getId(), $object2->getId(), $object3->getId()], $objectIds);

        $objectIds = $searchResultIdListService->getIdsForCurrentPage(
            (new DataObjectSearch())
                ->setClassDefinition($object1->getClass())
                ->addModifier(new OrderByFullPath(SortDirection::DESC))
                ->setPage(1)
                ->setPageSize(2)
        );

        $this->assertEquals([$object3->getId(), $object2->getId()], $objectIds);
    }

    public function testSearchDocumentIdList(): void
    {
        $document1 = TestHelper::createEmptyDocument()->setKey('document1')->save();
        $document2 = TestHelper::createEmptyDocument()->setKey('document2')->save();
        $document3 = TestHelper::createEmptyDocument()->setKey('document3')->save();

        /** @var SearchResultIdListServiceInterface $searchResultIdListService */
        $searchResultIdListService = $this->tester->grabService(SearchResultIdListServiceInterface::class);

        $documentIds = $searchResultIdListService->getAllIds(
            new DocumentSearch()
        );

        $this->assertIdArrayEquals([$document1->getId(), $document2->getId(), $document3->getId()], $documentIds);

        $documentIds = $searchResultIdListService->getIdsForCurrentPage(
            (new DocumentSearch())
                ->addModifier(new OrderByFullPath(SortDirection::DESC))
                ->setPage(1)
                ->setPageSize(2)
        );

        $this->assertEquals([$document3->getId(), $document2->getId()], $documentIds);
    }

    private function assertIdArrayEquals(array $ids1, array $ids2): void
    {
        sort($ids1);
        sort($ids2);
        $this->assertEquals($ids1, $ids2);
    }
}
