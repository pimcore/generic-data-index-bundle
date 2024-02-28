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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Asset\AssetSearchResult\AssetSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Tree\ChildrenCountAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Modifier\SearchModifierServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\AssetSearchResultDenormalizer;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Model\User;

/**
 * @internal
 */
final class SearchHelper implements SearchHelperInterface
{
    public const ASSET_SEARCH = 'asset_search';

    public function __construct(
        private readonly AssetSearchResultDenormalizer $denormalizer,
        private readonly PermissionServiceInterface $permissionService,
        private readonly RuntimeCacheResolverInterface $runtimeCacheResolver,
        private readonly SearchIndexServiceInterface $searchIndexService,
        private readonly SearchModifierServiceInterface $searchModifierService,
    ) {
    }

    public function performSearch(SearchInterface $search, string $indexName): SearchResult
    {
        $adapterSearch = $this->searchIndexService->createPaginatedSearch($search->getPage(), $search->getPageSize());
        $this->searchModifierService->applyModifiersFromSearch($search, $adapterSearch);

        return $this
            ->searchIndexService
            ->search($adapterSearch, $indexName);
    }

    /**
     * @return int[]
     */
    public function getChildrenCounts(
        SearchResult $searchResult,
        string $indexName,
        SearchInterface $search
    ): array {
        $parentIds = $searchResult->getIds();

        if (empty($parentIds)) {
            return [];
        }

        $childrenCountAggregation = new ChildrenCountAggregation($parentIds);

        $search->addModifier($childrenCountAggregation);

        $searchResult = $this->performSearch($search, $indexName);

        $childrenCounts = [];
        foreach($parentIds as $parentId) {
            $childrenCounts[$parentId] = 0;
        }

        if ($aggregation = $searchResult->getAggregation($childrenCountAggregation->getAggregationName())) {
            foreach($aggregation->getBuckets() as $bucket) {
                $childrenCounts[$bucket->getKey()] = $bucket->getDocCount();
            }
        }

        return $childrenCounts;
    }

    /**
     * @throws Exception
     */
    public function hydrateAssetSearchResultHits(
        SearchResult $searchResult,
        array $childrenCounts,
        ?User $user = null
    ): array {
        $result = [];

        foreach ($searchResult->getHits() as $hit) {
            $source = $hit->getSource();

            $source[FieldCategory::SYSTEM_FIELDS->value][SystemField::HAS_CHILDREN->value] =
                ($childrenCounts[$hit->getId()] ?? 0) > 0;

            $result = $this->denormalizer->denormalize(
                $source,
                AssetSearchResult::class
            );

            $this->runtimeCacheResolver->save($result, self::ASSET_SEARCH . '_' . $result->getId());
            $result->setPermissions(
                $this->permissionService->getAssetPermissions(
                    $result->getFullPath(),
                    $user
                )
            );
        }

        return $result;
    }
}
