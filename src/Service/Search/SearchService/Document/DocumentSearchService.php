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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Document;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\UserPermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DocumentSearchException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\DocumentSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DocumentWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Pagination\PaginationInfoServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\DocumentTypeAdapter;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
final readonly class DocumentSearchService implements DocumentSearchServiceInterface
{
    public function __construct(
        private DocumentTypeAdapter $documentTypeAdapter,
        private PaginationInfoServiceInterface $paginationInfoService,
        private RuntimeCacheResolverInterface $runtimeCacheResolver,
        private SearchHelper $searchHelper,
        private SearchProviderInterface $searchProvider
    ) {
    }

    /**
     * @throws DocumentSearchException
     */
    public function search(SearchInterface $documentSearch): DocumentSearchResult
    {
        $documentSearch = $this->searchHelper->addSearchRestrictions(
            search: $documentSearch,
            userPermission: UserPermissionTypes::DOCUMENTS->value,
            workspaceType: DocumentWorkspace::WORKSPACE_TYPE
        );

        $searchResult = $this->searchHelper->performSearch(
            search: $documentSearch,
            indexName: $this->documentTypeAdapter->getAliasIndexName()
        );

        $childrenCounts = $this->searchHelper->getChildrenCounts(
            searchResult: $searchResult,
            indexName: $this->documentTypeAdapter->getAliasIndexName(),
            search: $this->searchProvider->createDocumentSearch()
        );

        try {
            return new DocumentSearchResult(
                items: $this->searchHelper->hydrateSearchResultHits(
                    $searchResult,
                    $childrenCounts,
                    $documentSearch->getUser()
                ),
                pagination: $this->paginationInfoService->getPaginationInfoFromSearchResult(
                    searchResult: $searchResult,
                    page: $documentSearch->getPage(),
                    pageSize: $documentSearch->getPageSize()
                ),
                aggregations:  $searchResult->getAggregations(),
            );
        } catch (Exception $e) {
            throw new DocumentSearchException($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function byId(
        int $id,
        ?User $user = null,
        bool $forceReload = false
    ): ?DocumentSearchResultItem {
        $cacheKey = SearchHelper::DOCUMENT_SEARCH . '_' . $id;

        if ($forceReload) {
            $searchResult = $this->searchDocumentById($id, $user);
            $this->runtimeCacheResolver->save($searchResult, $cacheKey);

            return $searchResult;
        }

        try {
            $searchResult = $this->runtimeCacheResolver->load($cacheKey);
        } catch (Exception) {
            $searchResult = $this->searchDocumentById($id, $user);
        }

        return $searchResult;
    }

    /**
     * @throws Exception
     */
    private function searchDocumentById(int $id, ?User $user = null): ?DocumentSearchResultItem
    {
        $documentSearch = $this->searchProvider->createDocumentSearch();
        $documentSearch->setPageSize(1);
        $documentSearch->addModifier(new IdFilter($id));

        if ($user) {
            $documentSearch->setUser($user);
        }

        return $this->search($documentSearch)->getItems()[0] ?? null;
    }
}
