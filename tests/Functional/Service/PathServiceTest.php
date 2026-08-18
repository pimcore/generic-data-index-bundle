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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Service;

use Codeception\Attribute\Skip;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Tests\Support\Util\TestHelper;

class PathServiceTest extends \Codeception\Test\Unit
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

    #[Skip('Failed asserting that two strings are equal.')]
    public function testAssetPathRewrite()
    {

        $asset = TestHelper::createImageAsset();
        $folder = TestHelper::createAssetFolder();
        $asset
            ->setParent($folder)
            ->setKey('test-asset')
            ->save();

        $folder->setKey('test-folder')->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');

        $searchResultItem = $searchService->byId($asset->getId());

        $this->assertEquals('/test-folder/test-asset', $searchResultItem->getFullPath());
    }

    public function testDataObjectChildrenPathRewrite()
    {
        $folder = TestHelper::createObjectFolder();
        $object = TestHelper::createEmptyObject();
        $object
            ->setParent($folder)
            ->setKey('test-object')
            ->save();

        $folder->setKey('renamed-object-folder')->save();

        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');

        $searchResultItem = $searchService->byId($object->getId());

        $this->assertEquals('/renamed-object-folder/test-object', $searchResultItem->getFullPath());
    }

    public function testDocumentChildrenPathRewriteForNonFolderParent()
    {
        $parentPage = TestHelper::createEmptyDocumentPage();
        $childPage = TestHelper::createEmptyDocumentPage('', false);
        $childPage
            ->setParent($parentPage)
            ->setKey('child-page')
            ->save();

        $parentPage->setKey('renamed-page')->save();

        $searchService = $this->tester->grabService('generic-data-index.test.service.document-search-service');

        $searchResultItem = $searchService->byId($childPage->getId());

        $this->assertEquals('/renamed-page/child-page', $searchResultItem->getFullPath());
    }
}
