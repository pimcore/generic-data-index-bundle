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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\Search\Modifier\QueryLanguage;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\ParentIdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\QueryLanguage\TreePqlFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject\DataObjectSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Model\DataObject\Unittest;
use Pimcore\Tests\Support\Util\TestHelper;

class TreePqlFilterTest extends \Codeception\Test\Unit
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

    public function testTreePqlFilterWithRelevantFolderKeys(): void
    {
        $folderA = TestHelper::createObjectFolder('treeA_');
        $folderB = TestHelper::createObjectFolder('treeB_');

        /** @var Unittest $object1 */
        $object1 = TestHelper::createEmptyObject('obj1_', false);
        $object1->setParent($folderA)->setPublished(true)->save();

        /** @var Unittest $object2 */
        $object2 = TestHelper::createEmptyObject('obj2_', false);
        $object2->setParent($folderB)->setPublished(true)->save();

        /** @var Unittest $object3 */
        $object3 = TestHelper::createEmptyObject('obj3_');

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        // PQL matches only object1 (inside folderA).
        // Relevant folder keys: only folderA.
        // At root with ParentIdFilter(1): folderA appears as a matching folder.
        // object3 is at root but does NOT match PQL (different path), so excluded.
        $pqlQuery = 'fullPath LIKE "' . $folderA->getRealFullPath() . '/*"';

        $treePqlFilter = new TreePqlFilter(
            $pqlQuery,
            [$folderA->getKey()]
        );

        $dataObjectSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier($treePqlFilter)
            ->addModifier(new ParentIdFilter(1))
        ;
        $searchResult = $searchService->search($dataObjectSearch);

        $this->assertIdArrayEquals(
            [$folderA->getId()],
            $searchResult->getIds(),
            'TreePqlFilter should return relevant folder at root level'
        );
    }

    public function testTreePqlFilterWithEmptyFolderKeys(): void
    {
        $folder = TestHelper::createObjectFolder('emptyKeys_');

        /** @var Unittest $object1 */
        $object1 = TestHelper::createEmptyObject('obj1_', false);
        $object1->setParent($folder)->setPublished(true)->save();

        /** @var Unittest $object2 */
        $object2 = TestHelper::createEmptyObject('obj2_', false);
        $object2->setParent($folder)->setPublished(true)->save();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        // Empty folder keys + ParentIdFilter on folder: only non-folder PQL matches inside
        $pqlQuery = 'fullPath LIKE "' . $folder->getRealFullPath() . '/*"';
        $treePqlFilter = new TreePqlFilter($pqlQuery, []);

        $dataObjectSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier($treePqlFilter)
            ->addModifier(new ParentIdFilter($folder->getId()))
        ;
        $searchResult = $searchService->search($dataObjectSearch);

        $this->assertIdArrayEquals(
            [$object1->getId(), $object2->getId()],
            $searchResult->getIds(),
            'Empty folderKeys should return only non-folder PQL matches'
        );
    }

    public function testTreePqlFilterExcludesNonMatchingFolders(): void
    {
        $folderMatch = TestHelper::createObjectFolder('match_');
        $folderNoMatch = TestHelper::createObjectFolder('nomatch_');

        /** @var Unittest $object1 */
        $object1 = TestHelper::createEmptyObject('obj1_', false);
        $object1->setParent($folderMatch)->setPublished(true)->save();

        /** @var Unittest $object2 */
        $object2 = TestHelper::createEmptyObject('obj2_', false);
        $object2->setParent($folderNoMatch)->setPublished(true)->save();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        // PQL matches objects under both folders, but only folderMatch is relevant.
        $pqlQuery = 'fullPath LIKE "' . $folderMatch->getRealFullPath() . '/*"'
            . ' or fullPath LIKE "' . $folderNoMatch->getRealFullPath() . '/*"';

        $treePqlFilter = new TreePqlFilter(
            $pqlQuery,
            [$folderMatch->getKey()]
        );

        $dataObjectSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier($treePqlFilter)
            ->addModifier(new ParentIdFilter(1))
        ;
        $searchResult = $searchService->search($dataObjectSearch);

        // Only folderMatch should appear (folderNoMatch excluded despite having matching descendants)
        $this->assertIdArrayEquals(
            [$folderMatch->getId()],
            $searchResult->getIds(),
            'Only folders in relevantFolderKeys should be returned'
        );
    }

    public function testTreePqlFilterWithMultipleFolders(): void
    {
        $folderA = TestHelper::createObjectFolder('multiA_');
        $folderB = TestHelper::createObjectFolder('multiB_');
        $folderC = TestHelper::createObjectFolder('multiC_');

        /** @var Unittest $object1 */
        $object1 = TestHelper::createEmptyObject('obj1_', false);
        $object1->setParent($folderA)->setPublished(true)->save();

        /** @var Unittest $object2 */
        $object2 = TestHelper::createEmptyObject('obj2_', false);
        $object2->setParent($folderB)->setPublished(true)->save();

        /** @var Unittest $object3 */
        $object3 = TestHelper::createEmptyObject('obj3_', false);
        $object3->setParent($folderC)->setPublished(true)->save();

        /** @var Unittest $object4 */
        $object4 = TestHelper::createEmptyObject('obj4_');

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        // PQL matches objects under folderA and folderB only
        $pqlQuery = 'fullPath LIKE "' . $folderA->getRealFullPath() . '/*"'
            . ' or fullPath LIKE "' . $folderB->getRealFullPath() . '/*"';

        $treePqlFilter = new TreePqlFilter(
            $pqlQuery,
            [$folderA->getKey(), $folderB->getKey()]
        );

        $dataObjectSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier($treePqlFilter)
            ->addModifier(new ParentIdFilter(1))
        ;
        $searchResult = $searchService->search($dataObjectSearch);

        // At root level: folderA, folderB (matching folders); object4 does NOT match PQL
        $this->assertIdArrayEquals(
            [$folderA->getId(), $folderB->getId()],
            $searchResult->getIds(),
            'Multiple relevant folders should all appear at root'
        );
    }

    public function testTreePqlFilterInsideFolder(): void
    {
        $folder = TestHelper::createObjectFolder('inside_');

        /** @var Unittest $objectMatch */
        $objectMatch = TestHelper::createEmptyObject('objM_', false);
        $objectMatch->setParent($folder)->setPublished(true)->save();

        /** @var Unittest $objectNoMatch */
        $objectNoMatch = TestHelper::createEmptyObject('objNM_', false);
        $objectNoMatch->setParent($folder)->setPublished(true)->save();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        // PQL that only matches objectMatch by its fullPath
        $pqlQuery = 'fullPath = "' . $objectMatch->getRealFullPath() . '"';
        $treePqlFilter = new TreePqlFilter($pqlQuery, []);

        $dataObjectSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier($treePqlFilter)
            ->addModifier(new ParentIdFilter($folder->getId()))
        ;
        $searchResult = $searchService->search($dataObjectSearch);

        $this->assertIdArrayEquals(
            [$objectMatch->getId()],
            $searchResult->getIds(),
            'Inside a folder with empty folderKeys, only PQL-matching non-folders should appear'
        );
    }

    public function testTreePqlFilterNoMatches(): void
    {
        /** @var Unittest $object */
        $object = TestHelper::createEmptyObject('obj_');

        $folder = TestHelper::createObjectFolder('noMatch_');

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        $treePqlFilter = new TreePqlFilter('fullPath LIKE "/nonexistent_path_xyz/*"', []);

        $dataObjectSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier($treePqlFilter)
        ;
        $searchResult = $searchService->search($dataObjectSearch);

        $this->assertEmpty(
            $searchResult->getIds(),
            'No results when PQL matches nothing and no folder keys provided'
        );
    }

    public function testTreePqlFilterNestedHierarchy(): void
    {
        $topFolder = TestHelper::createObjectFolder('top_');
        $subFolder = TestHelper::createObjectFolder('sub_', false);
        $subFolder->setParent($topFolder)->save();
        $otherFolder = TestHelper::createObjectFolder('other_');

        /** @var Unittest $object1 */
        $object1 = TestHelper::createEmptyObject('obj1_', false);
        $object1->setParent($subFolder)->setPublished(true)->save();

        /** @var Unittest $object2 */
        $object2 = TestHelper::createEmptyObject('obj2_', false);
        $object2->setParent($topFolder)->setPublished(true)->save();

        /** @var Unittest $object3 */
        $object3 = TestHelper::createEmptyObject('obj3_', false);
        $object3->setParent($otherFolder)->setPublished(true)->save();

        /** @var DataObjectSearchServiceInterface $searchService */
        $searchService = $this->tester->grabService('generic-data-index.test.service.data-object-search-service');
        /** @var SearchProviderInterface $searchProvider */
        $searchProvider = $this->tester->grabService(SearchProviderInterface::class);

        // PQL matches all three objects (descendants of topFolder or otherFolder)
        $pqlQuery = 'fullPath LIKE "' . $topFolder->getRealFullPath() . '/*"'
            . ' or fullPath LIKE "' . $otherFolder->getRealFullPath() . '/*"';

        // At root level: both topFolder and otherFolder have PQL-matching descendants.
        $treePqlFilter = new TreePqlFilter(
            $pqlQuery,
            [$topFolder->getKey(), $otherFolder->getKey()]
        );

        $dataObjectSearch = $searchProvider
            ->createDataObjectSearch()
            ->addModifier($treePqlFilter)
            ->addModifier(new ParentIdFilter(1))
        ;
        $searchResult = $searchService->search($dataObjectSearch);

        // At root level: topFolder and otherFolder
        $this->assertIdArrayEquals(
            [$topFolder->getId(), $otherFolder->getId()],
            $searchResult->getIds(),
            'Both folders with matching descendants should appear at root level'
        );

        // Now expand topFolder — subFolder has matching content, plus object2 directly matches PQL.
        $treePqlFilterTop = new TreePqlFilter(
            $pqlQuery,
            [$subFolder->getKey()]
        );

        $dataObjectSearchTop = $searchProvider
            ->createDataObjectSearch()
            ->addModifier($treePqlFilterTop)
            ->addModifier(new ParentIdFilter($topFolder->getId()))
        ;
        $searchResultTop = $searchService->search($dataObjectSearchTop);

        // Inside topFolder: subFolder (has matching content) + object2 (non-folder, matches PQL)
        $this->assertIdArrayEquals(
            [$subFolder->getId(), $object2->getId()],
            $searchResultTop->getIds(),
            'Inside parent folder: sub-folder with matches + direct PQL-matching objects'
        );
    }

    private function assertIdArrayEquals(array $ids1, array $ids2, string $message = ''): void
    {
        sort($ids1);
        sort($ids2);
        $this->assertEquals($ids1, $ids2, $message);
    }
}
