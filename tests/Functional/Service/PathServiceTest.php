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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Message\EnqueueRelatedIdsMessage;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\PathServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Tests\Support\Util\TestHelper;
use Symfony\Component\Messenger\MessageBusInterface;

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

        // Get document versions before rename
        $assetDocBefore = $client->get([
            'index' => $indexName,
            'id' => $asset->getId(),
        ]);
        $assetVersionBefore = $assetDocBefore['_version'] ?? 'N/A';

        $folderDocBefore = $client->get([
            'index' => $indexName,
            'id' => $folder->getId(),
        ]);
        $folderVersionBefore = $folderDocBefore['_version'] ?? 'N/A';

        // Step 2b: Test if message bus dispatch works (theory: this throws and prevents rewriteChildrenIndexPaths)
        $messageBusError = 'none';
        try {
            /** @var MessageBusInterface $messageBus */
            $messageBus = $this->tester->grabService(MessageBusInterface::class);
            $messageBus->dispatch(
                new EnqueueRelatedIdsMessage(
                    $folder->getId(),
                    ElementType::ASSET,
                    'update',
                    false
                )
            );
        } catch (\Exception $e) {
            $messageBusError = get_class($e) . ': ' . $e->getMessage();
        }

        // Step 3: Rename folder
        $folder->setKey('test-folder')->save();

        // Step 4: Capture post-rename state
        $assetPathAfter = $pathService->getCurrentIndexFullPath($asset);
        $folderPathAfter = $pathService->getCurrentIndexFullPath($folder);

        $assetDocAfter = $client->get([
            'index' => $indexName,
            'id' => $asset->getId(),
        ]);
        $assetVersionAfter = $assetDocAfter['_version'] ?? 'N/A';
        $assetSourceAfter = $assetDocAfter['_source']['system_fields'] ?? [];

        $folderDocAfter = $client->get([
            'index' => $indexName,
            'id' => $folder->getId(),
        ]);
        $folderVersionAfter = $folderDocAfter['_version'] ?? 'N/A';

        // Step 5: If test would fail, try manual rewriteChildrenIndexPaths to prove it works
        $manualRewriteResult = 'not attempted';
        if ($assetPathAfter !== '/test-folder/test-asset') {
            try {
                $pathService->rewriteChildrenIndexPaths($folder);
                $assetPathAfterManualRewrite = $pathService->getCurrentIndexFullPath($asset);
                $manualRewriteResult = sprintf('success, assetPath=%s', $assetPathAfterManualRewrite);
            } catch (\Exception $e) {
                $manualRewriteResult = get_class($e) . ': ' . $e->getMessage();
            }
        }

        $renameLimit = $configService->getMaxSynchronousChildrenRenameLimit();

        $diagnostics = sprintf(
            "DIAGNOSTICS:\n"
            . "  renameLimit=%d\n"
            . "  folderPath: before='%s' after='%s'\n"
            . "  assetPath: before='%s' after='%s'\n"
            . "  assetVersion: before=%s after=%s (delta=%s)\n"
            . "  folderVersion: before=%s after=%s (delta=%s)\n"
            . "  messageBusDispatchTest: %s\n"
            . "  manualRewriteChildrenIndexPaths: %s\n"
            . "  assetSystemFieldsAfter: path='%s' fullPath='%s' key='%s' checksum=%s\n"
            . "  indexName='%s'\n"
            . "  folder.getRealFullPath()='%s' asset.getRealFullPath()='%s'",
            $renameLimit,
            $folderPathBefore, $folderPathAfter,
            $assetPathBefore, $assetPathAfter,
            $assetVersionBefore, $assetVersionAfter,
            (is_numeric($assetVersionBefore) && is_numeric($assetVersionAfter))
                ? ($assetVersionAfter - $assetVersionBefore) : '?',
            $folderVersionBefore, $folderVersionAfter,
            (is_numeric($folderVersionBefore) && is_numeric($folderVersionAfter))
                ? ($folderVersionAfter - $folderVersionBefore) : '?',
            $messageBusError,
            $manualRewriteResult,
            $assetSourceAfter['path'] ?? 'N/A',
            $assetSourceAfter['fullPath'] ?? 'N/A',
            $assetSourceAfter['key'] ?? 'N/A',
            $assetSourceAfter['checksum'] ?? 'N/A',
            $indexName,
            $folder->getRealFullPath(),
            $asset->getRealFullPath()
        );

        // Assert the result
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
