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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\BulkOperationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\PathServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Event\AssetEvents;
use Pimcore\Event\Model\AssetEvent;
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

        /** @var IndexServiceInterface $indexService */
        $indexService = $this->tester->grabService(IndexServiceInterface::class);

        /** @var BulkOperationServiceInterface $bulkService */
        $bulkService = $this->tester->grabService(BulkOperationServiceInterface::class);

        // Step 1: Create asset and folder, save asset under folder
        $asset = TestHelper::createImageAsset();
        $folder = TestHelper::createAssetFolder();
        $asset
            ->setParent($folder)
            ->setKey('test-asset')
            ->save();

        // Capture pre-rename state
        $folderPathBefore = $pathService->getCurrentIndexFullPath($folder);
        $assetPathBefore = $pathService->getCurrentIndexFullPath($asset);

        $assetDocBefore = $client->get(['index' => $indexName, 'id' => $asset->getId()]);
        $assetVersionBefore = $assetDocBefore['_version'] ?? 'N/A';
        $folderDocBefore = $client->get(['index' => $indexName, 'id' => $folder->getId()]);
        $folderVersionBefore = $folderDocBefore['_version'] ?? 'N/A';

        // Step 2: Disable the event subscriber so we can manually replicate the flow
        $eventDispatcher = $this->tester->grabService('event_dispatcher');
        $listeners = $eventDispatcher->getListeners(AssetEvents::POST_UPDATE);
        foreach ($listeners as $listener) {
            $eventDispatcher->removeListener(AssetEvents::POST_UPDATE, $listener);
        }

        // Step 3: Rename folder (DB only, no index update because subscriber is disabled)
        $folder->setKey('test-folder')->save();

        // Step 4: Now manually replicate what IndexQueueService::updateIndexQueue does, step by step

        // 4a: doHandleIndexData — adds folder to bulk buffer (NOT committed yet)
        $doHandleError = 'none';
        try {
            $indexService->updateIndexData($folder);
        } catch (\Exception $e) {
            $doHandleError = get_class($e) . ': ' . $e->getMessage();
        }

        // 4b: Check what getCurrentIndexFullPath returns BEFORE commit (should be OLD path)
        $folderPathInOsBeforeCommit = $pathService->getCurrentIndexFullPath($folder);

        // 4c: Check countDocumentsByPath BEFORE commit
        $countBeforeCommit = $client->search([
            'index' => $indexName,
            'track_total_hits' => true,
            'rest_total_hits_as_int' => true,
            'body' => [
                'query' => ['term' => ['system_fields.fullPath' => $folderPathInOsBeforeCommit]],
                'size' => 0,
            ],
        ]);
        $countBeforeCommitTotal = $countBeforeCommit['hits']['total'] ?? 'N/A';

        // 4d: rewriteChildrenIndexPaths BEFORE commit — capture exception if any
        $rewriteError = 'none';
        try {
            $pathService->rewriteChildrenIndexPaths($folder);
        } catch (\Exception $e) {
            $rewriteError = get_class($e) . ': ' . $e->getMessage();
        }

        // 4e: Check asset path AFTER rewrite but BEFORE commit
        $assetPathAfterRewriteBeforeCommit = $pathService->getCurrentIndexFullPath($asset);

        $assetDocAfterRewrite = $client->get(['index' => $indexName, 'id' => $asset->getId()]);
        $assetVersionAfterRewrite = $assetDocAfterRewrite['_version'] ?? 'N/A';

        $folderDocAfterRewrite = $client->get(['index' => $indexName, 'id' => $folder->getId()]);
        $folderVersionAfterRewrite = $folderDocAfterRewrite['_version'] ?? 'N/A';

        // 4f: Also do a manual updateByQuery to see the raw response
        $manualUbqResponse = 'N/A';
        $manualUbqError = 'none';
        try {
            $oldPath = $folderPathInOsBeforeCommit;
            $newPath = $folder->getRealFullPath();
            $ubqResult = $client->updateByQuery([
                'index' => $indexName,
                'refresh' => true,
                'conflicts' => 'proceed',
                'body' => [
                    'script' => [
                        'lang' => 'painless',
                        'source' => "ctx._source.system_fields.checksum = 0",
                        'params' => [],
                    ],
                    'query' => [
                        'term' => [
                            'system_fields.fullPath' => $oldPath,
                        ],
                    ],
                ],
            ]);
            $manualUbqResponse = json_encode($ubqResult);
        } catch (\Exception $e) {
            $manualUbqError = get_class($e) . ': ' . $e->getMessage();
        }

        // 4g: Now commit the bulk
        $commitError = 'none';
        try {
            $bulkService->commit();
        } catch (\Exception $e) {
            $commitError = get_class($e) . ': ' . $e->getMessage();
        }

        // Step 5: Final state
        $assetPathFinal = $pathService->getCurrentIndexFullPath($asset);
        $folderPathFinal = $pathService->getCurrentIndexFullPath($folder);

        $assetDocFinal = $client->get(['index' => $indexName, 'id' => $asset->getId()]);
        $assetVersionFinal = $assetDocFinal['_version'] ?? 'N/A';
        $assetSourceFinal = $assetDocFinal['_source']['system_fields'] ?? [];

        $folderDocFinal = $client->get(['index' => $indexName, 'id' => $folder->getId()]);
        $folderVersionFinal = $folderDocFinal['_version'] ?? 'N/A';

        // Also query OpenSearch server info to confirm which engine we're on
        $serverInfo = 'N/A';
        try {
            $info = $client->info();
            $serverInfo = ($info['version']['distribution'] ?? 'elasticsearch') . ' ' . ($info['version']['number'] ?? '?');
        } catch (\Exception $e) {
            $serverInfo = 'error: ' . $e->getMessage();
        }

        $diagnostics = sprintf(
            "STEP-BY-STEP DIAGNOSTICS (engine: %s):\n"
            . "  BEFORE RENAME:\n"
            . "    folderPath='%s' assetPath='%s'\n"
            . "    assetVersion=%s folderVersion=%s\n"
            . "  AFTER doHandleIndexData (bulk buffer has folder, NOT committed):\n"
            . "    doHandleError: %s\n"
            . "    folderPathInOS='%s' (should be OLD path)\n"
            . "    countDocsByPath('%s')=%s\n"
            . "    folder.getRealFullPath()='%s'\n"
            . "    oldPath==newPath? %s\n"
            . "  AFTER rewriteChildrenIndexPaths (BEFORE commit):\n"
            . "    rewriteError: %s\n"
            . "    assetPath='%s'\n"
            . "    assetVersion: %s->%s (delta=%s)\n"
            . "    folderVersion: %s->%s (delta=%s)\n"
            . "  MANUAL updateByQuery (simple checksum=0 script):\n"
            . "    response: %s\n"
            . "    error: %s\n"
            . "  AFTER commit:\n"
            . "    commitError: %s\n"
            . "    assetPath='%s' folderPath='%s'\n"
            . "    assetVersion: %s (total delta=%s)\n"
            . "    folderVersion: %s (total delta=%s)\n"
            . "    assetSystemFields: path='%s' fullPath='%s' key='%s' checksum=%s",
            $serverInfo,
            $folderPathBefore, $assetPathBefore,
            $assetVersionBefore, $folderVersionBefore,
            $doHandleError,
            $folderPathInOsBeforeCommit,
            $folderPathInOsBeforeCommit, $countBeforeCommitTotal,
            $folder->getRealFullPath(),
            $folderPathInOsBeforeCommit === $folder->getRealFullPath() ? 'YES (early return!)' : 'NO (proceeds)',
            $rewriteError,
            $assetPathAfterRewriteBeforeCommit,
            $assetVersionBefore, $assetVersionAfterRewrite,
            is_numeric($assetVersionBefore) && is_numeric($assetVersionAfterRewrite)
                ? ($assetVersionAfterRewrite - $assetVersionBefore) : '?',
            $folderVersionBefore, $folderVersionAfterRewrite,
            is_numeric($folderVersionBefore) && is_numeric($folderVersionAfterRewrite)
                ? ($folderVersionAfterRewrite - $folderVersionBefore) : '?',
            $manualUbqResponse,
            $manualUbqError,
            $commitError,
            $assetPathFinal, $folderPathFinal,
            $assetVersionFinal,
            is_numeric($assetVersionBefore) && is_numeric($assetVersionFinal)
                ? ($assetVersionFinal - $assetVersionBefore) : '?',
            $folderVersionFinal,
            is_numeric($folderVersionBefore) && is_numeric($folderVersionFinal)
                ? ($folderVersionFinal - $folderVersionBefore) : '?',
            $assetSourceFinal['path'] ?? 'N/A',
            $assetSourceFinal['fullPath'] ?? 'N/A',
            $assetSourceFinal['key'] ?? 'N/A',
            $assetSourceFinal['checksum'] ?? 'N/A'
        );

        $this->assertEquals(
            '/test-folder/test-asset',
            $assetPathFinal,
            $diagnostics
        );

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        $searchResultItem = $searchService->byId($asset->getId());
        $this->assertEquals('/test-folder/test-asset', $searchResultItem->getFullPath());
    }
}
