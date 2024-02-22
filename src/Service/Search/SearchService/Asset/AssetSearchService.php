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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Asset;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\AbstractSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\AssetSearchResultDenormalizer;

/**
 * @internal
 */
final class AssetSearchService extends AbstractSearchService implements AssetSearchServiceInterface
{
    public function __construct(
        private readonly AssetTypeAdapter $assetTypeAdapter,
        private readonly AssetSearchResultDenormalizer $denormalizer,
    ) {

    }

    public function search(AssetSearch $assetSearch): AssetSearchResult
    {
        $searchResult = $this->performSearch(
            search: $assetSearch,
            indexName: $this->assetTypeAdapter->getAliasIndexName()
        );

        $childrenCounts = $this->getChildrenCounts(
            searchResult: $searchResult,
            indexName: $this->assetTypeAdapter->getAliasIndexName()
        );

        return new AssetSearchResult(
            items: $this->hydrateSearchResultHits($searchResult, $childrenCounts),
            pagination: $this->paginationInfoService->getPaginationInfoFromSearchResult(
                searchResult: $searchResult,
                page: $assetSearch->getPage(),
                pageSize: $assetSearch->getPageSize()
            ),
        );
    }

    public function byId(
        int $id
    ): ?AssetSearchResultItem {
        $assetSearch = (new AssetSearch())
            ->setPageSize(1)
            ->addModifier(new IdFilter($id));

        return $this->search($assetSearch)->getItems()[0] ?? null;
    }

    /**
     * @return AssetSearchResultItem[]
     */
    private function hydrateSearchResultHits(SearchResult $searchResult, array $childrenCounts): array
    {
        $result = [];

        foreach ($searchResult->getHits() as $hit) {
            $source = $hit->getSource();

            $source[FieldCategory::SYSTEM_FIELDS->value][SystemField::HAS_CHILDREN->value] =
                ($childrenCounts[$hit->getId()] ?? 0) > 0;

            $result[] = $this->denormalizer->denormalize($source, AssetSearchResultItem::class);
        }

        return $result;
    }
}
