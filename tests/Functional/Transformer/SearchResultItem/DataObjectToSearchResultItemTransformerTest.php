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

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Transformer\SearchResultItem\DataObjectToSearchResultItemTransformerInterface;
use Pimcore\Tests\Support\Util\TestHelper;
use Symfony\Component\Serializer\SerializerInterface;

class DataObjectToSearchResultItemTransformerTest extends \Codeception\Test\Unit
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
         * @var DataObjectToSearchResultItemTransformerInterface $transformer
         */
        $transformer = $this->tester->grabService(DataObjectToSearchResultItemTransformerInterface::class);

        // create asset
        $object = TestHelper::createEmptyObject();

        $folder = TestHelper::createObjectFolder();
        $object
            ->setParent($folder)
            ->save();

        $this->assetItemEqualsIndexItem($transformer->transform($object));
        $folderItem = $transformer->transform($folder);
        $this->assetItemEqualsIndexItem($folderItem);
        $this->assertTrue($folderItem->isHasChildren());
    }

    private function assetItemEqualsIndexItem(DataObjectSearchResultItem $item)
    {

        /**
         * @var SerializerInterface $serializer
         */
        $serializer = $this->tester->grabService(SerializerInterface::class);

        /**
         * @var DataObjectSearchServiceInterface $searchService
         */
        $searchService = $this->tester->grabService(DataObjectSearchServiceInterface::class);

        $this->assertEquals($serializer->normalize($searchService->byId($item->getId(), null, true)), $serializer->normalize($item));
    }
}
