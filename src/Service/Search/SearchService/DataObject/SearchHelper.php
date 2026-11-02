<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\DataObject;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\SerializerContext;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\DataObjectSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\DataObject\SearchResult\DataObjectSearchResultItem;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\ElementSearchResultItemInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Source\SystemFieldsOnlySource;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultHit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\Search\Modifier\SearchModifierServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\PermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Permission\UserPermissionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading\DataObjectLazyLoadingHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\AbstractSearchHelper;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\DataObjectSearchResultDenormalizer;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Model\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @internal
 */
final class SearchHelper extends AbstractSearchHelper
{
    public const OBJECT_SEARCH = 'data_object_search';

    public function __construct(
        private readonly DataObjectSearchResultDenormalizer $denormalizer,
        // Lazy, because the lazy-loading handler depends on the data object search service, which depends on this.
        #[Autowire(lazy: true)]
        private readonly DataObjectLazyLoadingHandlerInterface $lazyLoadingHandler,
        private readonly PermissionServiceInterface $permissionService,
        private readonly RuntimeCacheResolverInterface $runtimeCacheResolver,
        private readonly SearchIndexServiceInterface $searchIndexService,
        private readonly SearchModifierServiceInterface $searchModifierService,
        private readonly UserPermissionServiceInterface $userPermissionService,
    ) {
        parent::__construct(
            $this->searchIndexService,
            $this->searchModifierService,
            $this->userPermissionService
        );
    }

    /**
     * Hydrates the hits of a data object search. With the SystemFieldsOnlySource modifier, the result items
     * carry the system fields of the hits and lazy-load their full search index data on first access.
     *
     * @return DataObjectSearchResultItem[]
     */
    public function hydrateDataObjectSearchResultHits(
        SearchResult $searchResult,
        array $childrenCounts,
        DataObjectSearchInterface $search
    ): array {
        if (!$this->hasSystemFieldsOnlySource($search)) {
            return $this->hydrateSearchResultHits($searchResult, $childrenCounts, $search->getUser());
        }

        $results = [];
        foreach ($searchResult->getHits() as $hit) {
            $results[] = $this->hydrateSystemFieldsOnlySearchResultHit($hit, $childrenCounts, $search->getUser());
        }

        return $results;
    }

    public function hydrateSearchResultHit(
        SearchResultHit $searchResultHit,
        array $childrenCounts,
        ?User $user = null
    ): ElementSearchResultItemInterface {
        $result = $this->denormalizeSearchResultHit($searchResultHit, $childrenCounts);

        $this->runtimeCacheResolver->save($result, self::OBJECT_SEARCH . '_' . $result->getId());
        $result->setPermissions(
            $this->permissionService->getDataObjectPermissions(
                $result,
                $user
            )
        );

        return $result;
    }

    private function hydrateSystemFieldsOnlySearchResultHit(
        SearchResultHit $searchResultHit,
        array $childrenCounts,
        ?User $user
    ): DataObjectSearchResultItem {
        // Not saved in the runtime cache: byId() would return the item without its search index data,
        // also to its own lazy-loading handler.
        $result = $this->lazyLoadingHandler->apply(
            $this->denormalizeSearchResultHit(
                $searchResultHit,
                $childrenCounts,
                SerializerContext::SKIP_SEARCH_INDEX_DATA->createContext()
            ),
            $user
        );

        $result->setPermissions(
            $this->permissionService->getDataObjectPermissions(
                $result,
                $user
            )
        );

        return $result;
    }

    private function denormalizeSearchResultHit(
        SearchResultHit $searchResultHit,
        array $childrenCounts,
        array $context = []
    ): DataObjectSearchResultItem {
        $source = $searchResultHit->getSource();

        $source[FieldCategory::SYSTEM_FIELDS->value][SystemField::HAS_CHILDREN->value] =
            ($childrenCounts[$searchResultHit->getId()] ?? 0) > 0;

        $result = $this->denormalizer->denormalize(
            $source,
            DataObjectSearchResult::class,
            null,
            $context
        );

        $result->setScore($searchResultHit->getScore());

        return $result;
    }

    private function hasSystemFieldsOnlySource(DataObjectSearchInterface $search): bool
    {
        foreach ($search->getModifiers() as $modifier) {
            if ($modifier instanceof SystemFieldsOnlySource) {
                return true;
            }
        }

        return false;
    }
}
