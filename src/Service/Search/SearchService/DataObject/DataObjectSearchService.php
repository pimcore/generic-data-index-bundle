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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\UserPermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DataObjectSearchException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DataObjectWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Pagination\PaginationInfoServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DataObjectTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
final readonly class DataObjectSearchService implements DataObjectSearchServiceInterface
{
    public function __construct(
        private DataObjectTypeAdapter $dataObjectTypeAdapter,
        private PaginationInfoServiceInterface $paginationInfoService,
        private RuntimeCacheResolverInterface $runtimeCacheResolver,
        private SearchHelper $searchHelper,
        private SearchProviderInterface $searchProvider
    ) {
    }

    /**
     * @throws DataObjectSearchException
     */
    public function search(SearchInterface|DataObjectSearchInterface $dataObjectSearch): DataObjectSearchResult
    {
        $indexContext = $dataObjectSearch->getClassDefinition() ?: DataObjectIndexHandler::DATA_OBJECT_INDEX_ALIAS;

        $dataObjectSearch = $this->searchHelper->addSearchRestrictions(
            search: $dataObjectSearch,
            userPermission: UserPermissionTypes::OBJECTS->value,
            workspaceType: DataObjectWorkspace::WORKSPACE_TYPE
        );

        $searchResult = $this->searchHelper->performSearch(
            search: $dataObjectSearch,
            indexName: $this->dataObjectTypeAdapter->getAliasIndexName($indexContext)
        );

        $childrenCounts = $this->searchHelper->getChildrenCounts(
            searchResult: $searchResult,
            indexName: $this->dataObjectTypeAdapter->getAliasIndexName($indexContext),
            search: $this->searchProvider->createAssetSearch()
        );

        try {
            return new DataObjectSearchResult(
                items: $this->searchHelper->hydrateSearchResultHits(
                    $searchResult,
                    $childrenCounts,
                    $dataObjectSearch->getUser()
                ),
                pagination: $this->paginationInfoService->getPaginationInfoFromSearchResult(
                    searchResult: $searchResult,
                    page: $dataObjectSearch->getPage(),
                    pageSize: $dataObjectSearch->getPageSize()
                ),
                aggregations: $searchResult->getAggregations(),
            );
        } catch (Exception $e) {
            throw new DataObjectSearchException($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function byId(
        int $id,
        ?User $user = null,
        bool $forceReload = false
    ): ?DataObjectSearchResultItem {
        $cacheKey = SearchHelper::OBJECT_SEARCH . '_' . $id;

        if ($forceReload) {
            $searchResult = $this->searchObjectById($id, $user);
            $this->runtimeCacheResolver->save($searchResult, $cacheKey);

            return $searchResult;
        }

        try {
            $searchResult = $this->runtimeCacheResolver->load($cacheKey);
        } catch (Exception) {
            $searchResult = $this->searchObjectById($id, $user);
        }

        return $searchResult;
    }

    /**
     * @throws Exception
     */
    private function searchObjectById(int $id, ?User $user = null): ?DataObjectSearchResultItem
    {
        $dataObjectSearch = $this->searchProvider->createDataObjectSearch();
        $dataObjectSearch->setPageSize(1);
        $dataObjectSearch->addModifier(new IdFilter($id));

        if ($user) {
            $dataObjectSearch->setUser($user);
        }

        return $this->search($dataObjectSearch)->getItems()[0] ?? null;
    }
}
