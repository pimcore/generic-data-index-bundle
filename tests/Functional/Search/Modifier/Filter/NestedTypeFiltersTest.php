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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\Filter;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IntegerFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\NestedFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\WildcardSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Model\DataObject\Unittest;
use Pimcore\Tests\Support\Util\TestHelper;

class NestedTypeFiltersTest extends Unit
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
    public function testNestedFilterWithIntegerFilter()
    {
        $seed = 68;
        /** @var Unittest $object1 */
        $object1 = $this->tester->createFullyFledgedObjectUnittest(seed: $seed);
        /** @var Unittest $object2 */
        $this->tester->createFullyFledgedObjectUnittest();
        /** @var Unittest $object3 */
        $object3 = $this->tester->createFullyFledgedObjectUnittest(seed: $seed);

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $dataObjectSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier(new NestedFilter(
                'structuredtable.row1',
                new IntegerFilter('col1', $seed + 1)
            ));

        $searchResult = $searchService->search($dataObjectSearch);
        $this->assertIdArrayContains([$object1->getId(), $object3->getId()], $searchResult->getIds());
    }

    // tests
    public function testNestedFilterWithWildcardFilter()
    {
        $seed = 33;
        /** @var Unittest $object1 */
        $object1 = $this->tester->createFullyFledgedObjectUnittest(seed: $seed);
        /** @var Unittest $object2 */
        $object2 = $this->tester->createFullyFledgedObjectUnittest();
        /** @var Unittest $object3 */
        $object3 = $this->tester->createFullyFledgedObjectUnittest(seed: $seed);

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $dataObjectSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier(new NestedFilter(
                'structuredtable.row1',
                new WildcardSearch('col2', 'text_a_' . $seed)
            ));

        $searchResult = $searchService->search($dataObjectSearch);
        $this->assertIdArrayContains([$object1->getId(), $object3->getId()], $searchResult->getIds());

        $dataObjectSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier(new NestedFilter(
                'structuredtable.row1',
                new WildcardSearch('col2', 'text_a_1')
            ));

        $searchResult = $searchService->search($dataObjectSearch);
        $this->assertEquals($object2->getId(), $searchResult->getIds()[0]);
    }

    private function assertIdArrayContains(array $expectedIds, array $actualIds): void
    {
        foreach ($expectedIds as $expectedId) {
            $this->assertContains($expectedId, $actualIds, "Expected ID {$expectedId} not found in result");
        }
    }
}
