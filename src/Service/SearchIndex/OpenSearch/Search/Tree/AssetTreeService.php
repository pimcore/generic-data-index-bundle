<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\Search\Tree;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Aggregation\Aggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Paging\PaginationInfo;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Tree\AssetTreeItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Tree\AssetTreeItemList;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Search\Tree\AssetTreeServiceInterface;

final class AssetTreeService implements AssetTreeServiceInterface
{
    public function __construct(
        private readonly SearchIndexServiceInterface $openSearchService,
        private readonly AssetTypeAdapter $assetTypeAdapter,
    ) {

    }

    public function fetchTreeItems(int $parentId = 1, int $page = 1, int $pageSize = 50): AssetTreeItemList
    {
        $openSearchResult = $this->fetchItems($parentId, $page, $pageSize);
        $treeItems = $this->listHitsById($openSearchResult['hits']);
        $childrenCounts = $this->fetchChildrenCounts(array_keys($treeItems));

        $pagination = new PaginationInfo(
            totalItems: $openSearchResult['total']['value'],
            page: $page,
            pageSize: $pageSize,
            totalPages: $pageSize > 0 ? (int)ceil($openSearchResult['total']['value'] / $pageSize) : 0
        );

        return new AssetTreeItemList(
            items: $this->hydrateTreeItems($treeItems, $childrenCounts),
            pagination: $pagination,
        );
    }

    /**
     * @return AssetTreeItem[]
     */
    private function hydrateTreeItems(array $items, array $childrenCounts): array
    {
        $resultItems = [];
        foreach ($items as $id => $item) {
            $systemFields = $item[FieldCategory::SYSTEM_FIELDS->value];

            $resultItems[] = new AssetTreeItem(
                id: $id,
                filename: $systemFields[FieldCategory\SystemField::KEY->value],
                children: $childrenCounts[$id] > 0,
            );
        }

        return $resultItems;
    }

    /**
     * @param int[] $parentIds
     */
    private function fetchChildrenCounts(array $parentIds): array
    {
        $parentIdAttribute = FieldCategory::SYSTEM_FIELDS->value . '.' . FieldCategory\SystemField::PARENT_ID->value;

        $search = new Search(
            size: 0
        );

        $search->addQuery(new BoolQuery([
            ConditionType::FILTER->value => [
                'terms' => [
                    $parentIdAttribute => $parentIds,
                ],
            ],
        ]));

        $search->addAggregation(new Aggregation(
            name: 'children_count',
            params: [
                'terms' => [
                    'field' => $parentIdAttribute,
                    'size' => count($parentIds),
                ],

            ]
        ));

        $openSearchResult = $this
            ->openSearchService
            ->search($search, $this->assetTypeAdapter->getAliasIndexName());

        $childrenCounts = [];
        foreach($parentIds as $parentId) {
            $childrenCounts[$parentId] = 0;
        }

        $aggregations = $openSearchResult->getAggregations();
        foreach($aggregations['children_count']['buckets'] as $bucket) {
            $childrenCounts[$bucket['key']] = $bucket['doc_count'];
        }

        return $childrenCounts;
    }

    private function fetchItems(int $parentId, int $page, int $pageSize): array
    {
        $parentIdAttribute = FieldCategory::SYSTEM_FIELDS->value . '.' . FieldCategory\SystemField::PARENT_ID->value;

        $from = $pageSize * ($page - 1);

        $search = new Search(
            from: $from,
            size: $pageSize,
            source: [
                FieldCategory::SYSTEM_FIELDS->value . '.' . FieldCategory\SystemField::ID->value,
                FieldCategory::SYSTEM_FIELDS->value . '.' . FieldCategory\SystemField::KEY->value,
                FieldCategory::SYSTEM_FIELDS->value . '.' . FieldCategory\SystemField::FULL_PATH->value,
            ]
        );

        $search->addQuery(new BoolQuery([
            ConditionType::FILTER->value => [
                'term' => [
                    $parentIdAttribute => $parentId,
                ],
            ],
        ]));

        $openSearchResult = $this
            ->openSearchService
            ->search($search, $this->assetTypeAdapter->getAliasIndexName());

        return $openSearchResult->getHits();
    }

    private function listHitsById(array $hits): array
    {
        $result = [];
        foreach ($hits as $hit) {
            $id = $hit['_source'][FieldCategory::SYSTEM_FIELDS->value][FieldCategory\SystemField::ID->value];
            $result[$id] = $hit['_source'];
        }

        return $result;
    }
}
