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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\OpenSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\Search\ModifierService\SearchModifierServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Search\SearchService\AssetSearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\AssetSearchResultDenormalizer;

final class AssetSearchService extends AbstractSearchService implements AssetSearchServiceInterface
{
    public function __construct(
        private readonly OpenSearchServiceInterface $openSearchService,
        private readonly AssetTypeAdapter $assetTypeAdapter,
        private readonly SearchModifierServiceInterface $searchModifierService,
        private readonly AssetSearchResultDenormalizer $denormalizer,
    ) {

    }

    public function search(AssetSearch $assetSearch): AssetSearchResult
    {
        $openSearchSearch = $this
            ->validateSearchModel($assetSearch)
            ->createPaginatedSearch(
                $assetSearch->getPage(),
                $assetSearch->getPageSize()
            );

        $this->searchModifierService->applyModifiersFromSearch(
            $this,
            $assetSearch,
            $openSearchSearch
        );

        $openSearchResult = $this
            ->openSearchService
            ->getOpenSearchClient()
            ->search([
                'index' => $this->assetTypeAdapter->getAliasIndexName(),
                'body' => $openSearchSearch->toArray(),
            ]);

        $openSearchResultHits = $openSearchResult['hits'];

        return new AssetSearchResult(
            items: $this->hydrateSearchResultHits($openSearchResultHits['hits']),
            pagination: $this->paginationInfoService->getPaginationInfoFromSearchResult(
                searchResult: $openSearchResultHits,
                page: $assetSearch->getPage(),
                pageSize: $assetSearch->getPageSize()
            ),
        );
    }

    /**
     * @param array $hits
     *
     * @return AssetSearchResultItem[]
     */
    private function hydrateSearchResultHits(array $hits): array
    {
        $result = [];

        foreach ($hits as $hit) {
            $result[] = $this->denormalizer->denormalize($hit['_source'], AssetSearchResultItem::class);
        }

        return $result;
    }
}
