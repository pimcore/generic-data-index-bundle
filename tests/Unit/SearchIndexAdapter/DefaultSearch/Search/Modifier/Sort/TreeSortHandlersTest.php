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
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
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
