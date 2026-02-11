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

        // If the path rewrite didn't work during save, capture detailed diagnostics
        if ($assetIndexedPathAfter !== '/test-folder/test-asset') {
            /** @var \Pimcore\SearchClient\SearchClientInterface $client */
            $client = $this->tester->getIndexSearchClient();
            $oldPath = $folderIndexedPath;
            $configService2 = $this->tester->grabService(SearchIndexConfigServiceInterface::class);
            $indexName = $configService2->getIndexName('asset');

            // Try running the exact same updateByQuery that PathService would run
            // using the OLD path (which is still on the asset)
            $updateByQueryResult = null;
            $updateByQueryError = null;
            try {
                $updateByQueryResult = $client->updateByQuery([
                    'index' => $indexName,
                    'refresh' => true,
                    'conflicts' => 'proceed',
                    'body' => [
                        'script' => [
                            'lang' => 'painless',
                            'source' => 'ctx._source.system_fields.fullPath = params.newPath + ctx._source.system_fields.key; ctx._source.system_fields.path = params.newPath;',
                            'params' => [
                                'newPath' => '/test-folder/',
                            ],
                        ],
                        'query' => [
                            'term' => [
                                'system_fields.fullPath' => $oldPath,
                            ],
                        ],
                    ],
                ]);
            } catch (\Exception $e) {
                $updateByQueryError = $e->getMessage();
            }

            // Check asset after our manual updateByQuery
            $assetPathAfterManualUBQ = $pathService->getCurrentIndexFullPath($asset);

            // Also try a match query instead of term to see if analysis difference matters
            $matchResult = $client->search([
                'index' => $indexName,
                'body' => [
                    'query' => ['match' => ['system_fields.fullPath' => $oldPath]],
                ],
            ]);
            $matchCount = $matchResult['hits']['total']['value'] ?? ($matchResult['hits']['total'] ?? 'N/A');

            // Also try with keyword subfield
            $keywordResult = $client->search([
                'index' => $indexName,
                'body' => [
                    'query' => ['wildcard' => ['system_fields.fullPath.keyword' => $oldPath . '/*']],
                ],
            ]);
            $keywordCount = $keywordResult['hits']['total']['value'] ?? ($keywordResult['hits']['total'] ?? 'N/A');

            // Get all docs to see what's in the index
            $allDocs = $client->search([
                'index' => $indexName,
                'body' => [
                    'query' => ['match_all' => (object)[]],
                    '_source' => ['system_fields.fullPath', 'system_fields.path', 'system_fields.key'],
                    'size' => 20,
                ],
            ]);
            $allDocsSummary = [];
            foreach ($allDocs['hits']['hits'] as $hit) {
                $allDocsSummary[] = sprintf(
                    'id=%s fullPath=%s',
                    $hit['_id'],
                    $hit['_source']['system_fields']['fullPath'] ?? 'N/A'
                );
            }

            $this->fail(sprintf(
                'DIAGNOSTIC: Asset path not rewritten. '
                . 'Asset path after save: "%s". '
                . 'Asset path after manual updateByQuery: "%s". '
                . 'updateByQuery result: %s. '
                . 'updateByQuery error: %s. '
                . 'match query count for old path: %s. '
                . 'keyword wildcard count for old path children: %s. '
                . 'All docs in index: [%s]. '
                . 'Folder path before rename: "%s". '
                . 'Index name: "%s"',
                $assetIndexedPathAfter,
                $assetPathAfterManualUBQ,
                json_encode($updateByQueryResult),
                $updateByQueryError ?? 'none',
                $matchCount,
                $keywordCount,
                implode(' | ', $allDocsSummary),
                $folderIndexedPath,
                $indexName
            ));
        }

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');

        $searchResultItem = $searchService->byId($asset->getId());

        $this->assertEquals('/test-folder/test-asset', $searchResultItem->getFullPath());
    }
}
