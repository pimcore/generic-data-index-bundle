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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\SearchService;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Search\SortDirection;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\HitData;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\RequiredByElementListServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Tests\Support\Util\TestHelper;

final class RequiredByElementListServiceTest extends Unit
{
    protected IndexTester $tester;

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

    public function testSearchDependencyList(): void
    {
        $asset = TestHelper::createImageAsset()->setKey('asset1')->save();
        $object1 = $this->createDependencyObject('object1', $asset);
        $object2 = $this->createDependencyObject('object2', $asset);
        $object3 = $this->createDependencyObject('object3', $asset);

        /** @var RequiredByElementListServiceInterface $dependencyService */
        $dependencyService = $this->tester->grabService(RequiredByElementListServiceInterface::class);

        $dependencyList = $dependencyService->getDependencyList($asset);
        $this->assertIdArrayEquals(
            [$object1->getId(), $object2->getId(), $object3->getId()],
            $this->getDependencyIds($dependencyList)
        );

        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);
        $elementSearch = $searchProvider->createElementSearch();

        $dependencyList = $dependencyService->getDependencyListForCurrentPage(
            $asset,
            ($elementSearch)
                ->addModifier(new OrderByFullPath(SortDirection::DESC))
                ->setPage(1)
                ->setPageSize(2)
        );
        $this->assertEquals(
            [$object3->getId(), $object2->getId()],
            $this->getDependencyIds($dependencyList)
        );

        $dependencyList = $dependencyService->getDependencyList(
            $asset,
            ($elementSearch)
                ->addModifier(new OrderByFullPath(SortDirection::DESC))
                ->setPage(1)
                ->setPageSize(2)
        );
        $this->assertEquals(
            [$object3->getId(), $object2->getId(), $object1->getId()],
            $this->getDependencyIds($dependencyList)
        );
    }

    private function createDependencyObject(string $key, Asset $asset): Concrete
    {
        return TestHelper::createEmptyObject()
            ->setKey($key)
            ->setImage($asset)
            ->save();
    }

    private function getDependencyIds(array $dependencyList): array
    {
        return array_map(fn (HitData $hit) => $hit->getId(), $dependencyList);
    }

    private function assertIdArrayEquals(array $ids1, array $ids2): void
    {
        sort($ids1);
        sort($ids2);
        $this->assertEquals($ids1, $ids2);
    }
}
