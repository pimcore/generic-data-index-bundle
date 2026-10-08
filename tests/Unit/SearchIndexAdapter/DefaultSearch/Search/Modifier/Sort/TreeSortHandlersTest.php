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
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContext;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSortList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\OrderByPageNumber;
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

    public function testCountIsSkippedForFrontPagesWithinTheItemsLimit(): void
    {
        $adapterSearch = $this->applyPageNumberSort(5000, 10, 3, itemsLimit: 100, expectedCountCalls: 0);

        $this->assertFalse($adapterSearch->isReverseItemOrder());
        $this->assertSame(20, $adapterSearch->getFrom());
        $this->assertSame(10, $adapterSearch->getSize());
        $this->assertSame(FieldSort::ORDER_ASC, $adapterSearch->getSortList()->getSort()[0]->getOrder());
    }

    public function testCountIsSkippedOnTheBoundary(): void
    {
        // 2 * 5 * 10 === 100
        $this->applyPageNumberSort(5000, 10, 5, itemsLimit: 100, expectedCountCalls: 0);
        $this->addToAssertionCount(1);
    }

    public function testCountIsExecutedJustAboveTheBoundary(): void
    {
        // 2 * 6 * 10 === 120 > 100: small result set, handler still leaves the search untouched
        $adapterSearch = $this->applyPageNumberSort(95, 10, 6, itemsLimit: 100, expectedCountCalls: 1);
        $this->assertFalse($adapterSearch->isReverseItemOrder());

        // deep page of a large result set is still read from the end
        $adapterSearch = $this->applyPageNumberSort(195, 10, 20, itemsLimit: 100, expectedCountCalls: 1);
        $this->assertTrue($adapterSearch->isReverseItemOrder());
        $this->assertSame(0, $adapterSearch->getFrom());
        $this->assertSame(5, $adapterSearch->getSize());
    }

    public function testCountIsNotExecutedWithoutSort(): void
    {
        $search = (new AssetSearch())->setPageSize(10)->setPage(8);
        $adapterSearch = new Search(from: 70, size: 10);

        $searchIndexService = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexService->expects($this->never())->method('getCount');

        (new TreeSortHandlers($searchIndexService, 2))->handleSortByPageNumber(
            new OrderByPageNumber(self::INDEX_NAME, $search),
            new SearchModifierContext($adapterSearch, $search)
        );
        $this->assertFalse($adapterSearch->isReverseItemOrder());
    }

    public function testGuardDoesNotChangeTheResultingSearchState(): void
    {
        foreach ([0, 1, 5, 95, 100, 101, 250, 1000, 5000] as $total) {
            foreach ([1, 7, 10, 25] as $pageSize) {
                foreach ([1, 2, 3, 5, 8, 10, 20, 50, 400] as $page) {
                    foreach ([2, 100, 120, 1000] as $itemsLimit) {
                        $guarded = $this->applyPageNumberSort($total, $pageSize, $page, $itemsLimit);
                        $reference = $this->applyPageNumberSortWithoutGuard($total, $pageSize, $page, $itemsLimit);

                        $context = sprintf('total=%d size=%d page=%d limit=%d', $total, $pageSize, $page, $itemsLimit);
                        $this->assertSame($reference->isReverseItemOrder(), $guarded->isReverseItemOrder(), $context);
                        $this->assertSame($reference->getFrom(), $guarded->getFrom(), $context);
                        $this->assertSame($reference->getSize(), $guarded->getSize(), $context);
                        $this->assertSame(
                            $reference->getSortList()->getSort()[0]->getOrder(),
                            $guarded->getSortList()->getSort()[0]->getOrder(),
                            $context
                        );
                    }
                }
            }
        }
    }

    /**
     * Reference implementation of the handler decision without the count guard.
     */
    private function applyPageNumberSortWithoutGuard(
        int $totalItems,
        int $pageSize,
        int $page,
        int $itemsLimit
    ): Search {
        $adapterSearch = new Search(from: $pageSize * ($page - 1), size: $pageSize);
        $adapterSearch->addSort(new FieldSort('system_fields.fullPath.sort', FieldSort::ORDER_ASC));

        $lastPage = (int)ceil($totalItems / $pageSize);
        if ($totalItems === 0 || $totalItems <= $itemsLimit || $page < $lastPage / 2 || $page > $lastPage) {
            return $adapterSearch;
        }

        $isLastPage = $page === $lastPage;
        $adapterSearch
            ->setReverseItemOrder(true)
            ->setFrom($isLastPage ? 0 : $totalItems - ($pageSize * $page))
            ->setSize($isLastPage ? $totalItems - ($pageSize * ($lastPage - 1)) : $pageSize)
            ->setSortList(new FieldSortList([new FieldSort('system_fields.fullPath.sort', FieldSort::ORDER_DESC)]));

        return $adapterSearch;
    }

    private function applyPageNumberSort(
        int $totalItems,
        int $pageSize,
        int $page,
        int $itemsLimit = 2,
        ?int $expectedCountCalls = null
    ): Search {
        $search = (new AssetSearch())
            ->setPageSize($pageSize)
            ->setPage($page);

        $adapterSearch = new Search(
            from: $pageSize * ($page - 1),
            size: $pageSize
        );
        $adapterSearch->addSort(new FieldSort('system_fields.fullPath.sort', FieldSort::ORDER_ASC));

        $this->createHandler($totalItems, $itemsLimit, $expectedCountCalls)->handleSortByPageNumber(
            new OrderByPageNumber(self::INDEX_NAME, $search),
            new SearchModifierContext($adapterSearch, $search)
        );

        return $adapterSearch;
    }

    private function createHandler(int $totalItems, int $itemsLimit, ?int $expectedCountCalls): TreeSortHandlers
    {
        $searchIndexService = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexService
            ->expects($expectedCountCalls === null ? $this->any() : $this->exactly($expectedCountCalls))
            ->method('getCount')
            ->willReturn($totalItems);

        return new TreeSortHandlers($searchIndexService, $itemsLimit);
    }
}
