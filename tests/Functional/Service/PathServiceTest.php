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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexName;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\PathServiceInterface;
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
        $this->tester->forceUpdateByQueryEmulation(false);
    }

    /**
     * Runs a test against the native _update_by_query API and against its search + bulk emulation.
     *
     * @return array<string, array{bool}>
     */
    public function updateByQueryModes(): array
    {
        return [
            'native update by query' => [false],
            'emulated update by query' => [true],
        ];
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

    /**
     * @dataProvider updateByQueryModes
     */
    public function testDataObjectChildrenPathRewrite(bool $emulateUpdateByQuery): void
    {
        $this->tester->forceUpdateByQueryEmulation($emulateUpdateByQuery);

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

    /**
     * @dataProvider updateByQueryModes
     */
    public function testDocumentChildrenPathRewriteForNonFolderParent(bool $emulateUpdateByQuery): void
    {
        $this->tester->forceUpdateByQueryEmulation($emulateUpdateByQuery);

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
     * @dataProvider updateByQueryModes
     */
    public function testAssetSubtreePathRewrite(bool $emulateUpdateByQuery): void
    {
        $this->tester->forceUpdateByQueryEmulation($emulateUpdateByQuery);
        $indexName = $this->tester->getIndexName(IndexName::ASSET->value);

        // /orig-folder/{child, sub/grand}
        $folder = TestHelper::createAssetFolder();
        $folder->setKey('orig-folder')->save();
        $child = TestHelper::createImageAsset();
        $child->setParent($folder)->setKey('child')->save();
        $subFolder = TestHelper::createAssetFolder();
        $subFolder->setParent($folder)->setKey('sub')->save();
        $grandChild = TestHelper::createImageAsset();
        $grandChild->setParent($subFolder)->setKey('grand')->save();

        $this->tester->flushIndex();

        // Renamed in memory only: the index still holds the old paths, the element reports the new one.
        $folder->setKey('renamed-folder');

        /** @var PathServiceInterface $pathService */
        $pathService = $this->tester->grabService(PathServiceInterface::class);
        $pathService->rewriteChildrenIndexPaths($folder);

        $this->tester->flushIndex();

        $fullPath = fn (int $id) => $this->tester->checkIndexEntry($id, $indexName)['_source']['system_fields']['fullPath'];
        $this->assertSame('/renamed-folder/child', $fullPath($child->getId()));
        $this->assertSame('/renamed-folder/sub', $fullPath($subFolder->getId()));
        $this->assertSame('/renamed-folder/sub/grand', $fullPath($grandChild->getId()));

        $grandChildFields = $this->tester->checkIndexEntry($grandChild->getId(), $indexName)['_source']['system_fields'];
        $this->assertSame('/renamed-folder/sub/', $grandChildFields['path']);
        $this->assertSame(
            [['level' => 1, 'name' => 'renamed-folder'], ['level' => 2, 'name' => 'sub']],
            $grandChildFields['pathLevels']
        );
    }
}
