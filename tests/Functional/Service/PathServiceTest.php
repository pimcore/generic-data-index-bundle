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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\PathService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AdapterServiceInterface;
use Pimcore\SearchClient\SearchClientInterface;
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

    /**
     * Regression test for PEES-2039: a thumbnail indexed as frontend URL (configured frontend prefix and
     * urlencoded path) must be rewritten as well when its folder is renamed, not only the plain path.
     */
    public function testAssetThumbnailPathRewriteWithFrontendPrefix()
    {
        $folder = TestHelper::createAssetFolder();
        $folder->setKey('thumb folder')->save();
        $prefixedAsset = TestHelper::createImageAsset();
        $prefixedAsset->setParent($folder)->setKey('prefixed-asset')->save();
        $plainAsset = TestHelper::createImageAsset();
        $plainAsset->setParent($folder)->setKey('plain-asset')->save();

        /** @var SearchClientInterface $client */
        $client = $this->tester->getIndexSearchClient();
        $indexName = $this->tester->getIndexName(IndexName::ASSET->value);

        $this->setIndexedThumbnail(
            $client,
            $indexName,
            $prefixedAsset->getId(),
            '/cdn-prefix/thumb%20folder/' . $prefixedAsset->getId() . '/image-thumb__preview/prefixed-asset.jpg'
        );
        $this->setIndexedThumbnail(
            $client,
            $indexName,
            $plainAsset->getId(),
            '/thumb folder/' . $plainAsset->getId() . '/image-thumb__preview/plain-asset.jpg'
        );

        $pathService = new PathService(
            $client,
            $this->tester->grabService(AdapterServiceInterface::class),
            ['source' => '', 'thumbnail' => '/cdn-prefix', 'thumbnail_deferred' => ''],
        );

        // rename without saving, so only the path service under test rewrites the children
        $folder->setKey('renamed thumb folder');
        $pathService->rewriteChildrenIndexPaths($folder);

        $this->assertSame(
            '/cdn-prefix/renamed%20thumb%20folder/' . $prefixedAsset->getId() . '/image-thumb__preview/prefixed-asset.jpg',
            $this->getIndexedThumbnail($client, $indexName, $prefixedAsset->getId())
        );
        $this->assertSame(
            '/renamed thumb folder/' . $plainAsset->getId() . '/image-thumb__preview/plain-asset.jpg',
            $this->getIndexedThumbnail($client, $indexName, $plainAsset->getId())
        );
    }

    private function setIndexedThumbnail(
        SearchClientInterface $client,
        string $indexName,
        int $id,
        string $thumbnail
    ): void {
        $document = $client->get(['index' => $indexName, 'id' => $id]);
        $source = $document['_source'];
        $source['system_fields']['thumbnail'] = $thumbnail;

        $client->index([
            'index' => $document['_index'],
            'id' => $id,
            'refresh' => true,
            'body' => $source,
        ]);
    }

    private function getIndexedThumbnail(SearchClientInterface $client, string $indexName, int $id): ?string
    {
        return $client->get(['index' => $indexName, 'id' => $id])['_source']['system_fields']['thumbnail'] ?? null;
    }
}
