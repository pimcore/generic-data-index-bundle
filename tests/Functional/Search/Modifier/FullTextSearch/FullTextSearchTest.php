<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\FullTextSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\ElementKeySearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\FullTextSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\MultiMatchSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\WildcardSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Tests\Support\Util\TestHelper;

final class FullTextSearchTest extends \Codeception\Test\Unit
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
    public function testElementKeySearch(): void
    {
        $asset = TestHelper::createImageAsset();
        $asset->setFilename('Test image 123.jpg')->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new ElementKeySearch('Test'))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new ElementKeySearch('Test*'))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new ElementKeySearch('*123.jpg'))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new ElementKeySearch('*123*'))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new ElementKeySearch('*123'))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([], $searchResult->getIds());
        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new ElementKeySearch('123*'))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([], $searchResult->getIds());

        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([], $searchResult->getIds());
        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new ElementKeySearch('123'))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([], $searchResult->getIds());

    }

    public function testWildcardSearch(): void
    {
        $asset = TestHelper::createImageAsset();
        $asset->setFilename('Test image 123.jpg')->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new WildcardSearch('fullPath', '*/Test*'))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new WildcardSearch('fullpath', '*/Test*'))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new WildcardSearch('filename', 'Test*'))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new WildcardSearch('fullPath', '/Test'))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new WildcardSearch('fullPath', '*123.jpg'))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new WildcardSearch('fullPath', '*123.jp'))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new WildcardSearch('fullPath', '*123.jp?'))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new WildcardSearch('system_fields.fullPath', '123', false))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([$asset->getId()], $searchResult->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new WildcardSearch('fullPath', '123', false))
        ;
        $searchResult = $searchService->search($assetSearch);
        $this->assertEquals([], $searchResult->getIds());
    }

    public function testFullTextSearch(): void
    {
        // full text search covers all index fields including the element id, so fixture
        // keys must not be numeric (element id collision) and must be single ngram-safe
        // tokens: the ngram tokenizer only emits letter/digit grams, so hyphenated
        // terms tokenized by whitespace at search time never match
        $asset = TestHelper::createImageAsset();
        $asset->setFilename('asset-one.jpg')->setKey('assetalpha')->save();
        $asset2 = TestHelper::createImageAsset();
        $asset2->setFilename('asset-two.jpg')->setKey('assetbeta')->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new FullTextSearch($asset->getKey()))
        ;
        $this->assertEquals([$asset->getId()], $searchService->search($assetSearch)->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new FullTextSearch($asset2->getKey()))
        ;
        $this->assertEquals([$asset2->getId()], $searchService->search($assetSearch)->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new FullTextSearch('asset'))
        ;
        $this->assertIdArrayEquals([$asset->getId(), $asset2->getId()], $searchService->search($assetSearch)->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new FullTextSearch($asset2->getFilename()))
        ;
        $this->assertEquals([$asset2->getId()], $searchService->search($assetSearch)->getIds());
    }

    public function testMultiMatchSearch(): void
    {
        // see testFullTextSearch: keys must not be numeric to avoid element id collisions
        $asset = TestHelper::createImageAsset();
        $asset->setFilename('asset-one.jpg')->setKey('assetalpha')->save();
        $asset2 = TestHelper::createImageAsset();
        $asset2->setFilename('asset-two.jpg')->setKey('assetbeta')->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new MultiMatchSearch($asset->getKey()))
        ;
        $this->assertEquals([$asset->getId()], $searchService->search($assetSearch)->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new MultiMatchSearch($asset2->getKey()))
        ;
        $this->assertEquals([$asset2->getId()], $searchService->search($assetSearch)->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new MultiMatchSearch(
                $asset2->getKey() . ' ' . $asset->getKey(),
                [],
                'cross_fields',
                'or')
            )
        ;
        $this->assertEquals([$asset->getId(), $asset2->getId()], $searchService->search($assetSearch)->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new MultiMatchSearch('asset'))
        ;
        $this->assertIdArrayEquals([$asset->getId(), $asset2->getId()], $searchService->search($assetSearch)->getIds());

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new MultiMatchSearch($asset2->getFilename()))
        ;
        $this->assertEquals([$asset2->getId()], $searchService->search($assetSearch)->getIds());
    }

    /**
     * The full text search covers all index fields including the element id. This
     * pins that behavior and documents why fixtures must not use numeric keys or
     * search terms: they collide with auto-increment element ids.
     *
     * @see https://github.com/pimcore/generic-data-index-bundle/issues/462
     */
    public function testFullTextSearchMatchesElementId(): void
    {
        $asset = TestHelper::createImageAsset();
        $asset2 = TestHelper::createImageAsset();
        $asset->setKey((string) $asset2->getId())->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.asset-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $assetSearch = $searchProvider
            ->createAssetSearch()
            ->addModifier(new FullTextSearch((string) $asset2->getId()))
        ;
        $this->assertIdArrayEquals(
            [$asset->getId(), $asset2->getId()],
            $searchService->search($assetSearch)->getIds()
        );
    }

    private function assertIdArrayEquals(array $ids1, array $ids2)
    {
        sort($ids1);
        sort($ids2);
        $this->assertEquals($ids1, $ids2);
    }
}
