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
        $asset = TestHelper::createImageAsset()->setKey('asset1')->save();
        $asset2 = TestHelper::createImageAsset()->setKey('asset2')->save();
        $asset3 = TestHelper::createImageAsset()->setKey('asset3')->save();

        /** @var FileSizeAggregationServiceInterface $fileSizeAggregationService */
        $fileSizeAggregationService = $this->tester->grabService(FileSizeAggregationServiceInterface::class);

        $fileSizeSum = $asset->getFileSize() + $asset2->getFileSize() + $asset3->getFileSize();

        $assetSearch = (new AssetSearch())
            ->addModifier(new IdsFilter([$asset->getId(), $asset2->getId(), $asset3->getId()]))
            ->setPageSize(3);

        $this->assertEquals($fileSizeSum, $fileSizeAggregationService->getFileSizeSum($assetSearch));
    }
}
