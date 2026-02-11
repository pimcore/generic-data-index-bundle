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
        /** @var \Pimcore\SearchClient\SearchClientInterface $client */
        $client = $this->tester->getIndexSearchClient();

        /** @var SearchIndexConfigServiceInterface $configService */
        $configService = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
        $indexName = $configService->getIndexName('asset');

        /** @var PathServiceInterface $pathService */
        $pathService = $this->tester->grabService(PathServiceInterface::class);

        // Step 1: Create asset and folder, save asset under folder
        $asset = TestHelper::createImageAsset();
        $folder = TestHelper::createAssetFolder();
        $asset
            ->setParent($folder)
            ->setKey('test-asset')
            ->save();

        // Step 2: Capture pre-rename state from OpenSearch
        $folderPathBefore = $pathService->getCurrentIndexFullPath($folder);
        $assetPathBefore = $pathService->getCurrentIndexFullPath($asset);

        // Get document versions before rename (to detect if updateByQuery touched them)
        $assetDocBefore = $client->get([
            'index' => $indexName,
            'id' => $asset->getId(),
        ]);
        $assetVersionBefore = $assetDocBefore['_version'] ?? 'N/A';
        $assetSeqNoBefore = $assetDocBefore['_seq_no'] ?? 'N/A';
        $assetPrimaryTermBefore = $assetDocBefore['_primary_term'] ?? 'N/A';

        $folderDocBefore = $client->get([
            'index' => $indexName,
            'id' => $folder->getId(),
        ]);
        $folderVersionBefore = $folderDocBefore['_version'] ?? 'N/A';

        // Replicate the countDocumentsByPath query that rewriteChildrenIndexPaths uses
        $countBeforeRename = $client->search([
            'index' => $indexName,
            'track_total_hits' => true,
            'rest_total_hits_as_int' => true,
            'body' => [
                'query' => [
                    'term' => [
                        'system_fields.fullPath' => $folderPathBefore,
                    ],
                ],
                'size' => 0,
            ],
        ]);
        $countBeforeRenameTotal = $countBeforeRename['hits']['total'] ?? 'N/A';

        // Step 3: Rename folder (triggers subscriber -> updateIndexQueue -> rewriteChildrenIndexPaths -> commit)
        $folder->setKey('test-folder')->save();

        // Step 4: Capture post-rename state
        $assetPathAfter = $pathService->getCurrentIndexFullPath($asset);
        $folderPathAfter = $pathService->getCurrentIndexFullPath($folder);

        $assetDocAfter = $client->get([
            'index' => $indexName,
            'id' => $asset->getId(),
        ]);
        $assetVersionAfter = $assetDocAfter['_version'] ?? 'N/A';
        $assetSeqNoAfter = $assetDocAfter['_seq_no'] ?? 'N/A';
        $assetSourceAfter = $assetDocAfter['_source']['system_fields'] ?? [];

        $folderDocAfter = $client->get([
            'index' => $indexName,
            'id' => $folder->getId(),
        ]);
        $folderVersionAfter = $folderDocAfter['_version'] ?? 'N/A';

        // Step 5: Check rename limit config
        $renameLimit = $configService->getMaxSynchronousChildrenRenameLimit();

        // Collect all diagnostics into one message
        $assetDocBeforeKeys = implode(',', array_keys($assetDocBefore));
        $diagnostics = sprintf(
            "DIAGNOSTICS:\n"
            . "  renameLimit=%d\n"
            . "  folderPath: before='%s' after='%s'\n"
            . "  assetPath: before='%s' after='%s'\n"
            . "  assetVersion: before=%s after=%s (delta=%s)\n"
            . "  assetSeqNo: before=%s after=%s\n"
            . "  assetPrimaryTerm: before=%s\n"
            . "  folderVersion: before=%s after=%s (delta=%s)\n"
            . "  countDocsByPath('%s') before rename=%s\n"
            . "  assetSystemFieldsAfter: path='%s' fullPath='%s' key='%s' checksum=%s\n"
            . "  assetDocBeforeKeys=[%s]\n"
            . "  indexName='%s'\n"
            . "  folder.getRealFullPath()='%s' asset.getRealFullPath()='%s'",
            $renameLimit,
            $folderPathBefore, $folderPathAfter,
            $assetPathBefore, $assetPathAfter,
            $assetVersionBefore, $assetVersionAfter,
            (is_numeric($assetVersionBefore) && is_numeric($assetVersionAfter))
                ? ($assetVersionAfter - $assetVersionBefore) : '?',
            $assetSeqNoBefore, $assetSeqNoAfter,
            $assetPrimaryTermBefore,
            $folderVersionBefore, $folderVersionAfter,
            (is_numeric($folderVersionBefore) && is_numeric($folderVersionAfter))
                ? ($folderVersionAfter - $folderVersionBefore) : '?',
            $folderPathBefore, $countBeforeRenameTotal,
            $assetSourceAfter['path'] ?? 'N/A',
            $assetSourceAfter['fullPath'] ?? 'N/A',
            $assetSourceAfter['key'] ?? 'N/A',
            $assetSourceAfter['checksum'] ?? 'N/A',
            $assetDocBeforeKeys,
            $indexName,
            $folder->getRealFullPath(),
            $asset->getRealFullPath()
        );

        // Assert the result — include diagnostics in failure message
        $this->assertEquals(
            '/test-folder/test-asset',
            $assetPathAfter,
            $diagnostics
        );

        // Also verify via the search service
        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        $searchResultItem = $searchService->byId($asset->getId());
        $this->assertEquals('/test-folder/test-asset', $searchResultItem->getFullPath());
    }
}
