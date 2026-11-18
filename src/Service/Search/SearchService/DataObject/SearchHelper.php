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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Permission\PermissionTypes;
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
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchResultItem\LazyLoading\FullSourceLazyLoadingHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\AbstractSearchHelper;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchProviderInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\Search\DataObjectSearchResultDenormalizer;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Model\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @internal
 */
final class SearchHelper extends AbstractSearchHelper
{
    public const OBJECT_SEARCH = 'data_object_search';

    public function __construct(
        // Lazy, because the data object search service depends on this helper.
        #[Autowire(lazy: true)]
        private readonly DataObjectSearchServiceInterface $dataObjectSearchService,
        private readonly DataObjectSearchResultDenormalizer $denormalizer,
        #[Autowire(service: 'monolog.logger.pimcore_generic_data_index')]
        private readonly LoggerInterface $logger,
        private readonly PermissionServiceInterface $permissionService,
        private readonly RuntimeCacheResolverInterface $runtimeCacheResolver,
        private readonly SearchIndexServiceInterface $searchIndexService,
        private readonly SearchModifierServiceInterface $searchModifierService,
        private readonly SearchProviderInterface $searchProvider,
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
     * carry the system fields of the hits and lazy-load their full search index data on first access, with the
     * user and the permission type of the search.
     *
     * @return DataObjectSearchResultItem[]
     */
    public function hydrateDataObjectSearchResultHits(
        SearchResult $searchResult,
        array $childrenCounts,
        DataObjectSearchInterface $search,
        PermissionTypes $permissionType
    ): array {
        if (!$this->hasSystemFieldsOnlySource($search)) {
            return $this->hydrateSearchResultHits($searchResult, $childrenCounts, $search->getUser());
        }

        $lazyLoadingHandler = new FullSourceLazyLoadingHandler(
            dataObjectSearchService: $this->dataObjectSearchService,
            logger: $this->logger,
            permissionType: $permissionType,
            searchProvider: $this->searchProvider,
            user: $search->getUser(),
        );

        $results = [];
        foreach ($searchResult->getHits() as $hit) {
            $results[] = $this->hydrateSystemFieldsOnlySearchResultHit(
                $hit,
                $childrenCounts,
                $lazyLoadingHandler,
                $search->getUser()
            );
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
        FullSourceLazyLoadingHandler $lazyLoadingHandler,
        ?User $user
    ): DataObjectSearchResultItem {
        // Not saved in the runtime cache: byId() would return the item without its search index data.
        $result = $this->denormalizeSearchResultHit(
            $searchResultHit,
            $childrenCounts,
            SerializerContext::SKIP_SEARCH_INDEX_DATA->createContext()
        )->withLazyLoadingHandler($lazyLoadingHandler);

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
