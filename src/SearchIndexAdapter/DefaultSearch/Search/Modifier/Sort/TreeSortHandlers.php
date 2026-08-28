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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\Sort;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\Search\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Search\SortDirection;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSortList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\DocumentSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\OrderByPageNumber;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByIndexField;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;

/**
 * @internal
 */
final class TreeSortHandlers
{
    public function __construct(
        private readonly SearchIndexServiceInterface $searchIndexService,
        private int $itemsLimit = 1000
    ) {
    }

    public function setItemsLimit(int $itemsLimit): void
    {
        $this->itemsLimit = $itemsLimit;
    }

    #[AsSearchModifierHandler]
    public function handleFullPathSort(
        OrderByFullPath $fullPathSort,
        SearchModifierContextInterface $context
    ): void {
        $context->getSearch()
            ->addSort(
                new FieldSort(
                    SystemField::FULL_PATH->getPath('sort'),
                    $fullPathSort->getDirection()->value
                )
            );
    }

    #[AsSearchModifierHandler]
    public function handleSortByPageNumber(
        OrderByPageNumber $pageNumberSort,
        SearchModifierContextInterface $context
    ): void {
        $contextSearch = $context->getSearch();
        $sortListItems = $contextSearch->getSortList()->getSort();
        if (empty($sortListItems)) {
            return;
        }

        $totalItems = $this->searchIndexService->getCount($contextSearch, $pageNumberSort->getIndexName());
        if ($totalItems === 0 || $totalItems <= $this->itemsLimit) {
            return;
        }

        $search = $pageNumberSort->getSearch();
        $pageSize = $search->getPageSize();
        $lastPage = (int)ceil($totalItems / $pageSize);
        $currentPage = $search->getPage();

        if ($currentPage < ($lastPage/2) ||
            $currentPage > $lastPage
        ) {
            return;
        }

        $invertedSortList = $this->getInvertedSortList($sortListItems);
        if (!empty($invertedSortList)) {
            $isLastPage = $currentPage === $lastPage;

            $contextSearch
                ->setReverseItemOrder(true)
                // Read from the end: the offset is the number of items that follow the requested
                // page. Deriving it from the page count instead would shift the whole window
                // towards the end whenever the last page is not completely filled.
                ->setFrom($isLastPage ? 0 : $totalItems - ($pageSize * $currentPage))
                ->setSize($isLastPage ? $totalItems - ($pageSize * ($lastPage - 1)) : $pageSize)
                ->setSortList(new FieldSortList($invertedSortList));
        }
    }

    #[AsSearchModifierHandler]
    public function handleIndexSort(
        OrderByIndexField $indexSort,
        SearchModifierContextInterface $context
    ): void {
        if (!$context->getOriginalSearch() instanceof DataObjectSearch &&
            !$context->getOriginalSearch() instanceof DocumentSearch
        ) {
            return;
        }

        $context->getSearch()
            ->addSort(
                new FieldSort(
                    SystemField::INDEX->getPath(),
                    $indexSort->getDirection()->value
                )
            );
    }

    private function getInvertedSortList(array $sortListItems): array
    {
        $invertedSortList = [];
        foreach ($sortListItems as $sortItem) {
            $sortItem->getOrder() === SortDirection::ASC->value
                ? $sortItem->setOrder(SortDirection::DESC->value)
                : $sortItem->setOrder(SortDirection::ASC->value);
            $invertedSortList[] = $sortItem;
        }

        return $invertedSortList;
    }
}
