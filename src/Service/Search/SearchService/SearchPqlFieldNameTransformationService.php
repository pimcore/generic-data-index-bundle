<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\QueryLanguage\PqlAdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CachedSearchIndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexEntityServiceInterface;

/**
 * @internal
 */
final readonly class SearchPqlFieldNameTransformationService implements SearchPqlFieldNameTransformationServiceInterface
{
    public function __construct(
        private IndexNameResolverInterface $indexNameResolver,
        private IndexEntityServiceInterface $indexEntityService,
        private CachedSearchIndexMappingServiceInterface $searchIndexMappingService,
        private PqlAdapterInterface $pqlAdapter,
    ) {
    }

    public function transformFieldnameForSearch(SearchInterface $search, string $fieldName): string
    {
        $indexEntity =  $this->indexEntityService->getByIndexName(
            $this->indexNameResolver->resolveIndexName($search)
        );
        $indexMapping = $this->searchIndexMappingService->getMapping($indexEntity->getIndexName());
        return $this->pqlAdapter->transformFieldName(
            $fieldName,
            $indexMapping,
            $indexEntity
        );
    }

}