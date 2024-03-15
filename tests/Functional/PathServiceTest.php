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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional;

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
}
