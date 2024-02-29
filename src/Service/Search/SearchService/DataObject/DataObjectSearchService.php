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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchResult\DataObjectSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Workspaces\WorkspaceQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Permission\Workspace\DataObjectWorkspace;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Pagination\PaginationInfoServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchHelperInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\AssetSearchResultDenormalizer;
use Pimcore\Model\User;

/**
 * @internal
 */
final class DataObjectSearchService implements DataObjectSearchInterface
{
    public function __construct(
        private readonly AssetTypeAdapter $assetTypeAdapter,
        private readonly AssetSearchResultDenormalizer $denormalizer,
        private readonly PaginationInfoServiceInterface $paginationInfoService,
        private readonly SearchHelperInterface $searchHelper,
        private readonly SearchProviderInterface $searchProvider
    ) {
    }

    public function search(SearchInterface $assetSearch): DataObjectSearchResult
    {
        $user = $assetSearch->getUser();
        if ($user && !$user->isAdmin()) {
            $assetSearch->addModifier(new WorkspaceQuery(
                DataObjectWorkspace::WORKSPACE_TYPE,
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

        return new DataObjectSearchResult(
            items: $this->hydrateSearchResultHits($searchResult, $childrenCounts, $user),
            pagination: $this->paginationInfoService->getPaginationInfoFromSearchResult(
                searchResult: $searchResult,
                page: $assetSearch->getPage(),
                pageSize: $assetSearch->getPageSize()
            ),
        );
    }

    public function byId(
        int $id,
        ?User $user = null
    ): ?DataObjectSearchResultItem {
        $assetSearch = $this->searchProvider->createAssetSearch();
        $assetSearch->setPageSize(1);
        $assetSearch->addModifier(new IdFilter($id));

        if ($user) {
            $assetSearch->setUser($user);
        }

        return $this->search($assetSearch)->getItems()[0] ?? null;
    }

    /**
     * @return DataObjectSearchResultItem[]
     */
    private function hydrateSearchResultHits(
        SearchResult $searchResult,
        array $childrenCounts,
        ?User $user = null
    ): array {
        $result = [];

        foreach ($searchResult->getHits() as $hit) {
            $source = $hit->getSource();

            $source[FieldCategory::SYSTEM_FIELDS->value][SystemField::HAS_CHILDREN->value] =
                ($childrenCounts[$hit->getId()] ?? 0) > 0;

            $result[] = $this->denormalizer->denormalize(
                $source,
                DataObjectSearchResultItem::class,
                null,
                ['user' => $user]
            );
        }

        return $result;
    }
}
