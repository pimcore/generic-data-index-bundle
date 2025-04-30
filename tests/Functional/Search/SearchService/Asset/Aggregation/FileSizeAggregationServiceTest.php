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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\SearchService\Asset\Aggregation;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\Aggregation\FileSizeAggregationServiceInterface;
use Pimcore\Tests\Support\Util\TestHelper;

final class FileSizeAggregationServiceTest extends \Codeception\Test\Unit
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

    public function testGetFileSizeSum(): void
    {
        $asset = TestHelper::createImageAsset();
        $asset2 = TestHelper::createImageAsset();
        $asset3 = TestHelper::createImageAsset();

        /** @var FileSizeAggregationServiceInterface $fileSizeAggregationService */
        $fileSizeAggregationService = $this->tester->grabService(FileSizeAggregationServiceInterface::class);

        $fileSizeSum = $asset->getFileSize() + $asset2->getFileSize() + $asset3->getFileSize();

        $assetSearch = (new AssetSearch())
            ->addModifier(new IdsFilter([$asset->getId(), $asset2->getId(), $asset3->getId()]))
            ->setPageSize(3);

        $this->assertEquals($fileSizeSum, $fileSizeAggregationService->getFileSizeSum($assetSearch));
        $this->assertNotSame(0, $fileSizeSum);
    }
}
