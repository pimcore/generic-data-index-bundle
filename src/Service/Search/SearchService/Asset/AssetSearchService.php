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

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResultItem;
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
        private readonly AssetTypeAdapter               $assetTypeAdapter,
        private readonly AssetSearchResultDenormalizer  $denormalizer,
    ) {

    }

    public function search(AssetSearch $assetSearch): AssetSearchResult
    {
        $searchResult = $this->searchWithPagination(
            search: $assetSearch,
            indexName: $this->assetTypeAdapter->getAliasIndexName()
        );


        return new AssetSearchResult(
            items: $this->hydrateSearchResultHits($searchResult),
            pagination: $this->paginationInfoService->getPaginationInfoFromSearchResult(
                searchResult: $searchResult,
                page: $assetSearch->getPage(),
                pageSize: $assetSearch->getPageSize()
            ),
        );
    }

    /**
     * @return AssetSearchResultItem[]
     */
    private function hydrateSearchResultHits(SearchResult $searchResult): array
    {
        $result = [];

        foreach ($searchResult->getHits() as $hit) {
            $result[] = $this->denormalizer->denormalize($hit->getSource(), AssetSearchResultItem::class);
        }

        return $result;
    }
}
