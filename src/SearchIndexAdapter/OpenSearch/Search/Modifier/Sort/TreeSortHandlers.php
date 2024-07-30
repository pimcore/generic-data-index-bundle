<?php
declare(strict_types=1);

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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\Sort;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Search\SortDirection;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort\FieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort\FieldSortList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\OrderByPageNumber;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;
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
            $contextSearch
                ->setReverseItemOrder(true)
                ->setFrom($pageSize * ($lastPage - $currentPage))
                ->setSize($currentPage === $lastPage ? $totalItems - ($pageSize * ($lastPage - 1)) : $pageSize)
                ->setSortList(new FieldSortList($invertedSortList));
        }
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
