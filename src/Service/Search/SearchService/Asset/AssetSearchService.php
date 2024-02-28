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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\UserPermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\AssetSearchException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Workspaces\WorkspaceQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\AssetWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Pagination\PaginationInfoServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\UserPermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchHelperInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Model\User;

/**
 * @internal
 */
final class AssetSearchService implements AssetSearchServiceInterface
{
    public function __construct(
        private readonly AssetTypeAdapter $assetTypeAdapter,
        private readonly PaginationInfoServiceInterface $paginationInfoService,
        private readonly UserPermissionServiceInterface $userPermissionService,
        private readonly SearchHelperInterface $searchHelper,
        private readonly SearchProviderInterface $searchProvider,
    ) {
    }

    /**
     * @throws AssetSearchException
     */
    public function search(SearchInterface $assetSearch): AssetSearchResult
    {
        $user = $assetSearch->getUser();
        if ($user && !$user->isAdmin()) {
            if ($this->userPermissionService->hasPermission($user, UserPermissionTypes::ASSETS->value)) {
                throw new AssetSearchException('User does not have permission to view assets');
            }
            $assetSearch->addModifier(new WorkspaceQuery(
                AssetWorkspace::WORKSPACE_TYPE,
                $user,
                PermissionTypes::VIEW->value
            ));
        }

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
                items: $this->searchHelper->hydrateAssetSearchResultHits(
                    $searchResult,
                    $childrenCounts,
                    $user
                ),
                pagination: $this->paginationInfoService->getPaginationInfoFromSearchResult(
                    searchResult: $searchResult,
                    page: $assetSearch->getPage(),
                    pageSize: $assetSearch->getPageSize()
                ),
            );
        } catch (Exception $e) {
            throw new AssetSearchException($e->getMessage());
        }
    }

    public function byId(
        int $id,
        ?User $user = null
    ): ?AssetSearchResultItem {
        $assetSearch = $this->searchProvider->createAssetSearch();
        $assetSearch->setPageSize(1);
        $assetSearch->addModifier(new IdFilter($id));

        if ($user) {
            $assetSearch->setUser($user);
        }

        return $this->search($assetSearch)->getItems()[0] ?? null;
    }
}
