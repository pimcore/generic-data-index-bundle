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

use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\PathServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
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

        // DIAGNOSTIC: Check maxSynchronousChildrenRenameLimit before rename
        /** @var SearchIndexConfigServiceInterface $configService */
        $configService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        $renameLimit = $configService->getMaxSynchronousChildrenRenameLimit();
        $searchSettings = $configService->getSearchSettings();
        $this->assertGreaterThan(
            0,
            $renameLimit,
            sprintf(
                'DIAGNOSTIC: maxSynchronousChildrenRenameLimit is %d (expected 500). searchSettings keys: [%s]. searchSettings dump: %s',
                $renameLimit,
                implode(', ', array_keys($searchSettings)),
                json_encode($searchSettings)
            )
        );

        // DIAGNOSTIC: Check folder is indexed before rename
        /** @var PathServiceInterface $pathService */
        $pathService = $this->tester->grabService(PathServiceInterface::class);
        $folderIndexedPath = $pathService->getCurrentIndexFullPath($folder);
        $this->assertNotNull(
            $folderIndexedPath,
            'DIAGNOSTIC: Folder is NOT indexed in OpenSearch before rename'
        );
        $this->assertNotEmpty(
            $folderIndexedPath,
            'DIAGNOSTIC: Folder indexed path is empty before rename'
        );

        // DIAGNOSTIC: Check asset is indexed before rename
        $assetIndexedPathBefore = $pathService->getCurrentIndexFullPath($asset);
        $this->assertNotNull(
            $assetIndexedPathBefore,
            'DIAGNOSTIC: Asset is NOT indexed in OpenSearch before rename'
        );

        $folder->setKey('test-folder')->save();

        // DIAGNOSTIC: Check folder indexed path after rename (should be /test-folder)
        $folderIndexedPathAfter = $pathService->getCurrentIndexFullPath($folder);
        $this->assertEquals(
            '/test-folder',
            $folderIndexedPathAfter,
            sprintf(
                'DIAGNOSTIC: Folder indexed path after rename is "%s" (expected "/test-folder"). Path before rename was "%s"',
                $folderIndexedPathAfter,
                $folderIndexedPath
            )
        );

        // DIAGNOSTIC: Check asset indexed path after rename (the actual test)
        $assetIndexedPathAfter = $pathService->getCurrentIndexFullPath($asset);
        $this->assertEquals(
            '/test-folder/test-asset',
            $assetIndexedPathAfter,
            sprintf(
                'DIAGNOSTIC: Asset indexed path after rename is "%s" (expected "/test-folder/test-asset"). '
                . 'Asset path before rename was "%s". '
                . 'Folder path before rename was "%s". '
                . 'maxSynchronousChildrenRenameLimit=%d',
                $assetIndexedPathAfter,
                $assetIndexedPathBefore,
                $folderIndexedPath,
                $renameLimit
            )
        );

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');

        $searchResultItem = $searchService->byId($asset->getId());

        $this->assertEquals('/test-folder/test-asset', $searchResultItem->getFullPath());
    }
}
