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

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\DocumentSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\ParentIdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\LocateInTreeServiceInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Folder;
use Pimcore\Model\Asset\Service;
use Pimcore\Model\Document;
use Pimcore\Tests\Support\Util\TestHelper;

class LocateInTreeServiceTest extends \Codeception\Test\Unit
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

    public function testGetPageNumber(): void
    {
        $this->createTestAssetFolders();

        $assetSearch = (new AssetSearch())
            ->addModifier(new OrderByFullPath())
            ->addModifier(new ParentIdFilter(1))
            ->setPageSize(10);

        $this->assertAssetPageNumber(1, '/test-folder-000', $assetSearch);
        $this->assertAssetPageNumber(1, '/test-folder-009', $assetSearch);
        $this->assertAssetPageNumber(2, '/test-folder-010', $assetSearch);
        $this->assertAssetPageNumber(2, '/test-folder-019', $assetSearch);
        $this->assertAssetPageNumber(3, '/test-folder-020', $assetSearch);
        $this->assertAssetPageNumber(3, '/test-folder-029', $assetSearch);
        $this->assertAssetPageNumber(5, '/test-folder-049', $assetSearch);
        $this->assertAssetPageNumber(6, '/test-folder-050', $assetSearch);
        $this->assertAssetPageNumber(null, 0, $assetSearch);

        $assetSearch = (new AssetSearch())
            ->addModifier(new OrderByFullPath())
            ->addModifier(new ParentIdFilter(1))
            ->setPageSize(4);

        $this->assertAssetPageNumber(1, '/test-folder-000', $assetSearch);
        $this->assertAssetPageNumber(1, '/test-folder-003', $assetSearch);
        $this->assertAssetPageNumber(2, '/test-folder-004', $assetSearch);
        $this->assertAssetPageNumber(5, '/test-folder-016', $assetSearch);
    }

    public function testDataObjectSearch(): void
    {
        $object = $this->tester->createFullyFledgedObjectSimple();

        $dataObjectSearch = (new DataObjectSearch())
            ->setClassDefinition($object->getClass())
            ->addModifier(new OrderByFullPath())
            ->addModifier(new ParentIdFilter(1))
            ->setPageSize(4);

        /** @var LocateInTreeServiceInterface $locateInTreeService */
        $locateInTreeService = $this->tester->grabService(LocateInTreeServiceInterface::class);

        $this->assertEquals(1, $locateInTreeService->getPageNumber($dataObjectSearch, $object->getId()));
        $this->assertEquals(null, $locateInTreeService->getPageNumber($dataObjectSearch, 123456));
    }

    public function testDocumentSearch(): void
    {
        $document = Document::getById(1);
        $document->save();

        $documentSearch = (new DocumentSearch())
            ->addModifier(new OrderByFullPath())
            ->setPageSize(4);

        /** @var LocateInTreeServiceInterface $locateInTreeService */
        $locateInTreeService = $this->tester->grabService(LocateInTreeServiceInterface::class);

        $this->assertEquals(1, $locateInTreeService->getPageNumber($documentSearch, $document->getId()));
        $this->assertEquals(null, $locateInTreeService->getPageNumber($documentSearch, 123456));
    }

    private function assertAssetPageNumber(?int $expectedPageNumber, string|int $asset, AssetSearch $assetSearch): void
    {
        /** @var LocateInTreeServiceInterface $locateInTreeService */
        $locateInTreeService = $this->tester->grabService(LocateInTreeServiceInterface::class);

        $assetId = is_int($asset) ? $asset : Asset::getByPath($asset)->getId();

        $result = $locateInTreeService->getPageNumber($assetSearch, $assetId);
        $this->assertSame($expectedPageNumber, $result);
    }

    private function createTestAssetFolders(): void
    {
        $folder = Folder::getById(1);
        $folder->save();

        for ($i=0; $i<=50; $i++) {
            $folderName = '/test-folder-'.str_pad($i, 3, '0', STR_PAD_LEFT);
            Service::createFolderByPath($folderName);
        }
    }
}
