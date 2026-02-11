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

        // DIAGNOSTIC: Check asset indexed path after rename
        $assetIndexedPathAfter = $pathService->getCurrentIndexFullPath($asset);

        // If the path rewrite didn't work during save, try calling it manually
        // to see if it throws an exception or if the conditions aren't met
        if ($assetIndexedPathAfter !== '/test-folder/test-asset') {
            // Check what getCurrentIndexFullPath returns for the folder NOW
            // (after commit - should be /test-folder)
            $folderPathNow = $pathService->getCurrentIndexFullPath($folder);

            // Manually attempt the rewrite and capture any exception
            // Note: this will likely return early because folder's indexed path
            // now matches getRealFullPath() after commit
            $rewriteException = null;
            try {
                $pathService->rewriteChildrenIndexPaths($folder);
            } catch (\Exception $e) {
                $rewriteException = $e->getMessage();
            }

            // Now try a direct updateByQuery using the old path to see if OpenSearch can do it
            /** @var \Pimcore\SearchClient\SearchClientInterface $client */
            $client = $this->tester->getIndexSearchClient();

            // First, search for docs with the old folder path to understand the index state
            $oldPath = $folderIndexedPath; // the path before rename e.g. /698c7043453b912
            $configService2 = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
            $indexName = $configService2->getIndexName('asset');

            // Count docs matching old path via term query on fullPath
            $countOldPath = $client->search([
                'index' => $indexName,
                'track_total_hits' => true,
                'rest_total_hits_as_int' => true,
                'body' => [
                    'query' => ['term' => ['system_fields.fullPath' => $oldPath]],
                    'size' => 0,
                ],
            ]);
            $countOld = $countOldPath['hits']['total'] ?? 'N/A';

            // Count docs matching new path
            $countNewPath = $client->search([
                'index' => $indexName,
                'track_total_hits' => true,
                'rest_total_hits_as_int' => true,
                'body' => [
                    'query' => ['term' => ['system_fields.fullPath' => '/test-folder']],
                    'size' => 0,
                ],
            ]);
            $countNew = $countNewPath['hits']['total'] ?? 'N/A';

            // Get the asset document directly by ID to see its actual data
            $assetDoc = $client->get([
                'index' => $indexName,
                'id' => $asset->getId(),
            ]);
            $assetDocPath = $assetDoc['_source']['system_fields']['fullPath'] ?? 'N/A';
            $assetDocSysPath = $assetDoc['_source']['system_fields']['path'] ?? 'N/A';

            // Flush to ensure visibility
            $this->tester->flushIndex();

            // Check asset path after manual rewrite attempt
            $assetPathAfterManualRewrite = $pathService->getCurrentIndexFullPath($asset);

            $this->fail(sprintf(
                'DIAGNOSTIC: Asset path not rewritten during save. '
                . 'Asset indexed path after save: "%s". '
                . 'Asset indexed path after manual rewrite: "%s". '
                . 'Folder indexed path after save: "%s". '
                . 'Folder realFullPath: "%s". '
                . 'Folder indexed path before rename: "%s". '
                . 'Rewrite exception: %s. '
                . 'maxSynchronousChildrenRenameLimit=%d. '
                . 'Docs matching old path "%s": %s. '
                . 'Docs matching new path "/test-folder": %s. '
                . 'Asset doc fullPath (direct GET): "%s". '
                . 'Asset doc path (direct GET): "%s". '
                . 'Index name: "%s"',
                $assetIndexedPathAfter,
                $assetPathAfterManualRewrite,
                $folderPathNow,
                $folder->getRealFullPath(),
                $folderIndexedPath,
                $rewriteException ?? 'none',
                $renameLimit,
                $oldPath,
                $countOld,
                $countNew,
                $assetDocPath,
                $assetDocSysPath,
                $indexName
            ));
        }

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');

        $searchResultItem = $searchService->byId($asset->getId());

        $this->assertEquals('/test-folder/test-asset', $searchResultItem->getFullPath());
    }
}
