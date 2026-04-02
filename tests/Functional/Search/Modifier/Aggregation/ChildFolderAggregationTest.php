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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\Aggregation;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Tree\ChildFolderAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\ExcludeFoldersFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\PathFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\QueryLanguage\PqlFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Model\DataObject\Unittest;
use Pimcore\Tests\Support\Util\TestHelper;

final class ChildFolderAggregationTest extends \Codeception\Test\Unit
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

    public function testChildFolderAggregationReturnsChildFolderNames(): void
    {
        $folderA = TestHelper::createObjectFolder('cfaA_');
        $folderB = TestHelper::createObjectFolder('cfaB_');

        /** @var Unittest $object1 */
        $object1 = TestHelper::createEmptyObject('obj1_', false);
        $object1->setParent($folderA)->setInput('match')->setPublished(true)->save();

        /** @var Unittest $object2 */
        $object2 = TestHelper::createEmptyObject('obj2_', false);
        $object2->setParent($folderB)->setInput('match')->setPublished(true)->save();

        /** @var Unittest $object3 */
        $object3 = TestHelper::createEmptyObject('obj3_');
        $object3->setInput('match')->save();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        // Simulate the real usage: PathFilter + ExcludeFoldersFilter + PqlFilter + ChildFolderAggregation
        $aggregation = new ChildFolderAggregation('/');

        $dataObjectSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier(new PathFilter('/'))
            ->addModifier(new ExcludeFoldersFilter())
            ->addModifier(new PqlFilter('input = "match"'))
            ->addModifier($aggregation)
            ->setClassDefinition($object1->getClass())
        ;
        $dataObjectSearch->setAggregationsOnly(true);

        $searchResult = $searchService->search($dataObjectSearch);
        $folderNames = $this->extractFolderNames($searchResult, $aggregation->getAggregationName());

        sort($folderNames);
        $expectedNames = [$folderA->getKey(), $folderB->getKey()];
        sort($expectedNames);

        $this->assertEquals(
            $expectedNames,
            $folderNames,
            'Aggregation should return folder names of direct children containing matching objects'
        );
    }

    public function testChildFolderAggregationNestedLevels()
    {
        $topFolder = TestHelper::createObjectFolder('cfaTop_');
        $subFolderA = TestHelper::createObjectFolder('cfaSub1_', false);
        $subFolderA->setParent($topFolder)->save();
        $subFolderB = TestHelper::createObjectFolder('cfaSub2_', false);
        $subFolderB->setParent($topFolder)->save();

        /** @var Unittest $object1 */
        $object1 = TestHelper::createEmptyObject('obj1_', false);
        $object1->setParent($subFolderA)->setInput('deep')->setPublished(true)->save();

        /** @var Unittest $object2 */
        $object2 = TestHelper::createEmptyObject('obj2_', false);
        $object2->setParent($subFolderB)->setInput('deep')->setPublished(true)->save();

        /** @var Unittest $object3 */
        $object3 = TestHelper::createEmptyObject('obj3_', false);
        $object3->setParent($topFolder)->setInput('deep')->setPublished(true)->save();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        // Query from root — should find topFolder at child level 1
        $aggregationRoot = new ChildFolderAggregation('/');

        $rootSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier(new PathFilter('/'))
            ->addModifier(new ExcludeFoldersFilter())
            ->addModifier(new PqlFilter('input = "deep"'))
            ->addModifier($aggregationRoot)
            ->setClassDefinition($object1->getClass())
        ;
        $rootSearch->setAggregationsOnly(true);

        $rootResult = $searchService->search($rootSearch);
        $rootFolderNames = $this->extractFolderNames($rootResult, $aggregationRoot->getAggregationName());

        $this->assertEquals(
            [$topFolder->getKey()],
            $rootFolderNames,
            'At root level, only topFolder should appear (not subFolders)'
        );

        // Query from topFolder — should find subFolderA and subFolderB at child level 2
        $aggregationTop = new ChildFolderAggregation($topFolder->getRealFullPath());

        $topSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier(new PathFilter($topFolder->getRealFullPath()))
            ->addModifier(new ExcludeFoldersFilter())
            ->addModifier(new PqlFilter('input = "deep"'))
            ->addModifier($aggregationTop)
            ->setClassDefinition($object1->getClass())
        ;
        $topSearch->setAggregationsOnly(true);

        $topResult = $searchService->search($topSearch);
        $topFolderNames = $this->extractFolderNames($topResult, $aggregationTop->getAggregationName());

        sort($topFolderNames);
        $expectedSubNames = [$subFolderA->getKey(), $subFolderB->getKey()];
        sort($expectedSubNames);

        $this->assertEquals(
            $expectedSubNames,
            $topFolderNames,
            'At topFolder level, subFolderA and subFolderB should appear'
        );
    }

    public function testChildFolderAggregationNoMatches()
    {
        $folder = TestHelper::createObjectFolder('cfaEmpty_');

        /** @var Unittest $object */
        $object = TestHelper::createEmptyObject('obj_', false);
        $object->setParent($folder)->setInput('something')->setPublished(true)->save();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $aggregation = new ChildFolderAggregation('/');

        $dataObjectSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier(new PathFilter('/'))
            ->addModifier(new ExcludeFoldersFilter())
            ->addModifier(new PqlFilter('input = "nonexistent_value"'))
            ->addModifier($aggregation)
            ->setClassDefinition($object->getClass())
        ;
        $dataObjectSearch->setAggregationsOnly(true);

        $searchResult = $searchService->search($dataObjectSearch);
        $folderNames = $this->extractFolderNames($searchResult, $aggregation->getAggregationName());

        $this->assertEmpty($folderNames, 'No folder names when PQL matches nothing');
    }

    public function testChildFolderAggregationOnlyMatchingFolders()
    {
        $folderWithMatch = TestHelper::createObjectFolder('cfaWith_');
        $folderWithoutMatch = TestHelper::createObjectFolder('cfaWithout_');

        /** @var Unittest $object1 */
        $object1 = TestHelper::createEmptyObject('obj1_', false);
        $object1->setParent($folderWithMatch)->setInput('target')->setPublished(true)->save();

        /** @var Unittest $object2 */
        $object2 = TestHelper::createEmptyObject('obj2_', false);
        $object2->setParent($folderWithoutMatch)->setInput('other')->setPublished(true)->save();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $aggregation = new ChildFolderAggregation('/');

        $dataObjectSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier(new PathFilter('/'))
            ->addModifier(new ExcludeFoldersFilter())
            ->addModifier(new PqlFilter('input = "target"'))
            ->addModifier($aggregation)
            ->setClassDefinition($object1->getClass())
        ;
        $dataObjectSearch->setAggregationsOnly(true);

        $searchResult = $searchService->search($dataObjectSearch);
        $folderNames = $this->extractFolderNames($searchResult, $aggregation->getAggregationName());

        $this->assertEquals(
            [$folderWithMatch->getKey()],
            $folderNames,
            'Only folders containing PQL-matching objects should appear in aggregation'
        );

        $this->assertNotContains(
            $folderWithoutMatch->getKey(),
            $folderNames,
            'Folders without matching content must not appear'
        );
    }

    private function extractFolderNames($searchResult, string $aggregationName): array
    {
        $aggregation = $searchResult->getAggregation($aggregationName);
        if ($aggregation === null) {
            return [];
        }

        $result = $aggregation->getAggregationResult();
        $buckets = $result['filtered_level']['folder_names']['buckets'] ?? [];

        return array_map(
            static fn (array $bucket): string => (string) $bucket['key'],
            $buckets,
        );
    }
}
