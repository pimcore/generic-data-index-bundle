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
final readonly class AssetSearchService implements AssetSearchServiceInterface
{
    public function __construct(
        private AssetTypeAdapter $assetTypeAdapter,
        private PaginationInfoServiceInterface $paginationInfoService,
        private RuntimeCacheResolverInterface $runtimeCacheResolver,
        private SearchHelper $searchHelper,
        private SearchProviderInterface $searchProvider
    ) {
    }

    /**
     * @throws AssetSearchException
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
     * @throws AssetSearchException
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
     * @throws AssetSearchException
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
