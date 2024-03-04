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

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\UserPermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\AssetSearchException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\SearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\AssetWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Pagination\PaginationInfoServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
final class AssetSearchService implements AssetSearchServiceInterface
{
    public function __construct(
        private readonly AssetTypeAdapter $assetTypeAdapter,
        private readonly PaginationInfoServiceInterface $paginationInfoService,
        private readonly RuntimeCacheResolverInterface $runtimeCacheResolver,
        private readonly SearchHelper $searchHelper,
        private readonly SearchProviderInterface $searchProvider
    ) {
    }

    /**
     * @throws Exception
     */
    public function search(SearchInterface $assetSearch): AssetSearchResult
    {
        $assetSearch = $this->searchHelper->addSearchRestrictions(
            search: $assetSearch,
            userPermission: UserPermissionTypes::ASSETS->value,
            workspaceType: AssetWorkspace::WORKSPACE_TYPE
        );

        $searchResult = $this->searchHelper->performSearch(
            search: $assetSearch,
            indexName: $this->assetTypeAdapter->getAliasIndexName()
        );

        $childrenCounts = $this->searchHelper->getChildrenCounts(
            searchResult: $searchResult,
            indexName: $this->assetTypeAdapter->getAliasIndexName(),
            search: $this->searchProvider->createAssetSearch()
        );

        try {
            return new AssetSearchResult(
                items: $this->searchHelper->hydrateSearchResultHits(
                    $searchResult,
                    $childrenCounts,
                    $assetSearch->getUser()
                ),
                pagination: $this->paginationInfoService->getPaginationInfoFromSearchResult(
                    searchResult: $searchResult,
                    page: $assetSearch->getPage(),
                    pageSize: $assetSearch->getPageSize()
                ),
                aggregations: $searchResult->getAggregations(),
            );
        } catch (Exception $e) {
            throw new AssetSearchException($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function byId(
        int $id,
        ?User $user = null,
        bool $forceReload = false
    ): ?AssetSearchResultItem {
        $cacheKey = SearchHelper::ASSET_SEARCH . '_' . $id;

        if ($forceReload) {
            $searchResult = $this->searchAssetById($id, $user);
            $this->runtimeCacheResolver->save($searchResult, $cacheKey);

            return $searchResult;
        }

        try {
            $searchResult = $this->runtimeCacheResolver->load($cacheKey);
        } catch (Exception) {
            $searchResult = $this->searchAssetById($id, $user);
        }

        return $searchResult;
    }

    /**
     * @throws Exception
     */
    private function searchAssetById(int $id, ?User $user = null): ?AssetSearchResultItem
    {
        $assetSearch = $this->searchProvider->createAssetSearch();
        $assetSearch->setPageSize(1);
        $assetSearch->addModifier(new IdFilter($id));

        if ($user) {
            $assetSearch->setUser($user);
        }

        return $this->search($assetSearch)->getItems()[0] ?? null;
    }
}
