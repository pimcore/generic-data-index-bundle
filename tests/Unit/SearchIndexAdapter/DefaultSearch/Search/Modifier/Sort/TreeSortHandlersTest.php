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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DefaultSearch\Search\Modifier\Sort;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Search\SortDirection;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContext;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\OrderByPageNumber;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFoldersFirst;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\Sort\TreeSortHandlers;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;

/**
 * @internal
 */
final class TreeSortHandlersTest extends Unit
{
    private const INDEX_NAME = 'test_index';

    /**
     * Pages in the upper half of the result set are read from the end with an inverted sort, so
     * that deep paging never has to walk past the middle of the result set. The inverted window
     * has to describe the very same slice as the ascending one - which only holds when the offset
     * is derived from the total item count, not from the (rounded up) number of pages.
     */
    public function testInvertedWindowMatchesTheAscendingWindowWhenTheLastPageIsNotFull(): void
    {
        $totalItems = 95;
        $pageSize = 10;
        $page = 8;

        $adapterSearch = $this->applyPageNumberSort($totalItems, $pageSize, $page);

        // Ascending page 8 covers the items 71-80, which are the items 16-25 counted from the end.
        // The page-count based offset would be 20, returning the items 66-75 instead.
        $this->assertTrue($adapterSearch->isReverseItemOrder());
        $this->assertSame(15, $adapterSearch->getFrom());
        $this->assertSame(10, $adapterSearch->getSize());
    }

    public function testInvertedWindowMatchesTheAscendingWindowWhenTheLastPageIsFull(): void
    {
        $totalItems = 100;
        $pageSize = 10;
        $page = 8;

        $adapterSearch = $this->applyPageNumberSort($totalItems, $pageSize, $page);

        // Ascending page 8 covers the items 71-80, which are the items 21-30 counted from the end.
        $this->assertTrue($adapterSearch->isReverseItemOrder());
        $this->assertSame(20, $adapterSearch->getFrom());
        $this->assertSame(10, $adapterSearch->getSize());
    }

    public function testLastPageOnlyReadsTheRemainingItems(): void
    {
        $totalItems = 95;
        $pageSize = 10;
        $page = 10;

        $adapterSearch = $this->applyPageNumberSort($totalItems, $pageSize, $page);

        // Ascending page 10 covers the items 91-95, which are the items 1-5 counted from the end.
        $this->assertTrue($adapterSearch->isReverseItemOrder());
        $this->assertSame(0, $adapterSearch->getFrom());
        $this->assertSame(5, $adapterSearch->getSize());
    }

    public function testSortIsInvertedForPagesInTheUpperHalf(): void
    {
        $adapterSearch = $this->applyPageNumberSort(95, 10, 8);

        $sort = $adapterSearch->getSortList()->getSort();
        $this->assertCount(1, $sort);
        $this->assertSame(FieldSort::ORDER_DESC, $sort[0]->getOrder());
    }

    public function testPagesInTheLowerHalfAreLeftUntouched(): void
    {
        $adapterSearch = $this->applyPageNumberSort(95, 10, 3);

        $this->assertFalse($adapterSearch->isReverseItemOrder());
        $this->assertSame(20, $adapterSearch->getFrom());
        $this->assertSame(10, $adapterSearch->getSize());
        $this->assertSame(FieldSort::ORDER_ASC, $adapterSearch->getSortList()->getSort()[0]->getOrder());
    }

    public function testSmallResultSetsAreLeftUntouched(): void
    {
        $adapterSearch = $this->applyPageNumberSort(95, 10, 8, itemsLimit: 1000);

        $this->assertFalse($adapterSearch->isReverseItemOrder());
        $this->assertSame(70, $adapterSearch->getFrom());
        $this->assertSame(10, $adapterSearch->getSize());
    }

    public function testPagesBeyondTheLastPageAreLeftUntouched(): void
    {
        $adapterSearch = $this->applyPageNumberSort(95, 10, 11);

        $this->assertFalse($adapterSearch->isReverseItemOrder());
        $this->assertSame(100, $adapterSearch->getFrom());
        $this->assertSame(10, $adapterSearch->getSize());
    }

    private function applyPageNumberSort(
        int $totalItems,
        int $pageSize,
        int $page,
        int $itemsLimit = 2
    ): Search {
        $search = (new AssetSearch())
            ->setPageSize($pageSize)
            ->setPage($page);

        $adapterSearch = new Search(
            from: $pageSize * ($page - 1),
            size: $pageSize
        );
        $adapterSearch->addSort(new FieldSort('system_fields.fullPath.sort', FieldSort::ORDER_ASC));

        $this->createHandler($totalItems, $itemsLimit)->handleSortByPageNumber(
            new OrderByPageNumber(self::INDEX_NAME, $search),
            new SearchModifierContext($adapterSearch, $search)
        );

        return $adapterSearch;
    }

    private function createHandler(int $totalItems, int $itemsLimit): TreeSortHandlers
    {
        $searchIndexService = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexService
            ->method('getCount')
            ->willReturn($totalItems);

        return new TreeSortHandlers($searchIndexService, $itemsLimit);
    }

    public function testFoldersFirstSortIsAddedBeforeFullPathSort(): void
    {
        $search = new Search();
        $context = $this->createContext($search);
        $handlers = new TreeSortHandlers($this->createMock(SearchIndexServiceInterface::class));

        $handlers->handleFoldersFirstSort(new OrderByFoldersFirst(), $context);
        $handlers->handleFullPathSort(new OrderByFullPath(SortDirection::ASC), $context);

        $sortList = $search->getSortList()->toArray();

        $this->assertCount(2, $sortList);
        $this->assertSame(['_script'], array_keys($sortList[0]));
        $this->assertSame('number', $sortList[0]['_script']['type']);
        $this->assertSame('asc', $sortList[0]['_script']['order']);
        $this->assertSame('folder', $sortList[0]['_script']['script']['params']['folderType']);
        $this->assertStringContainsString("doc['system_fields.type']", $sortList[0]['_script']['script']['source']);
        $this->assertSame(['system_fields.fullPath.sort' => ['order' => 'asc']], $sortList[1]);
    }

    public function testFoldersFirstSortIsInvertedTogetherWithThePathSortForLastPages(): void
    {
        $search = new Search();
        $context = $this->createContext($search);
        $searchIndexService = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexService->method('getCount')->willReturn(10);
        $handlers = new TreeSortHandlers($searchIndexService, 2);

        $handlers->handleFoldersFirstSort(new OrderByFoldersFirst(), $context);
        $handlers->handleFullPathSort(new OrderByFullPath(SortDirection::ASC), $context);
        $handlers->handleSortByPageNumber(
            new OrderByPageNumber('asset_index', (new AssetSearch())->setPage(5)->setPageSize(2)),
            $context
        );

        $sortList = $search->getSortList()->toArray();

        $this->assertTrue($search->isReverseItemOrder());
        $this->assertSame('desc', $sortList[0]['_script']['order']);
        $this->assertSame(['system_fields.fullPath.sort' => ['order' => 'desc']], $sortList[1]);
    }

    private function createContext(Search $search): SearchModifierContextInterface
    {
        $context = $this->createMock(SearchModifierContextInterface::class);
        $context->method('getSearch')->willReturn($search);
        $context->method('getOriginalSearch')->willReturn(new AssetSearch());

        return $context;
    }
}
