<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Transformer\SearchResultItem;

use OpenSearch\Common\Exceptions\Missing404Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem\Document;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem\Folder;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem\Image;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\SearchResultItem\Video;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Transformer\SearchResultItem\AssetToSearchResultItemTransformerInterface;
use Pimcore\Tests\Support\Util\TestHelper;
use Symfony\Component\Serializer\SerializerInterface;

class AssetToSearchResultItemTransformerTest extends \Codeception\Test\Unit
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

    public function testTransform()
    {
        /**
         * @var AssetToSearchResultItemTransformerInterface $transformer
         */
        $transformer = $this->tester->grabService(AssetToSearchResultItemTransformerInterface::class);

        // create asset
        $asset = TestHelper::createImageAsset();

        $folder = TestHelper::createAssetFolder();
        $asset
            ->setParent($folder)
            ->save();

        $this->assetItemEqualsIndexItem($transformer->transform($asset));
        $folderItem = $transformer->transform($folder);
        $this->assetItemEqualsIndexItem($folderItem);
        $this->assertTrue($folderItem->isHasChildren());
    }

    private function assetItemEqualsIndexItem(AssetSearchResultItem $item)
    {

        /**
         * @var SerializerInterface $serializer
         */
        $serializer = $this->tester->grabService(SerializerInterface::class);

        /**
         * @var AssetSearchServiceInterface $searchService
         */
        $searchService = $this->tester->grabService(AssetSearchServiceInterface::class);

        $this->assertEquals($serializer->normalize($searchService->byId($item->getId(), null, true)), $serializer->normalize($item));
    }
}
