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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\Filter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Dependency\RequiredByFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Dependency\RequiresFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Model\DataObject\Unittest;
use Pimcore\Tests\Support\Util\TestHelper;

class DependencyFiltersTest extends \Codeception\Test\Unit
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
    public function testDependencyFilters()
    {
        /**
         * @var Unittest $object1
         * @var Unittest $object2
         * @var Unittest $object3
         * @var Unittest $object4
         */
        $object1 = TestHelper::createEmptyObject();
        $object2 = TestHelper::createEmptyObject();
        $object3 = TestHelper::createEmptyObject();
        $object4 = TestHelper::createEmptyObject();

        $object1
            ->setObjects([$object2, $object3])
            ->save()
        ;

        $object4->setObjects([$object1])->save();

        /** @var AssetSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.element-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $elementSearch = $searchProvider
            ->createElementSearch()
            ->addModifier(new RequiredByFilter($object1->getId(), ElementType::DATA_OBJECT))
        ;
        $searchResult = $searchService->search($elementSearch);
        $this->assertIdArrayEquals([$object4->getId()], $searchResult->getIds());

        $elementSearch = $searchProvider
            ->createElementSearch()
            ->addModifier(new RequiresFilter($object1->getId(), ElementType::DATA_OBJECT));
        $searchResult = $searchService->search($elementSearch);
        $this->assertIdArrayEquals([$object2->getId(), $object3->getId()], $searchResult->getIds());
    }

    private function assertIdArrayEquals(array $ids1, array $ids2)
    {
        sort($ids1);
        sort($ids2);
        $this->assertEquals($ids1, $ids2);
    }
}
