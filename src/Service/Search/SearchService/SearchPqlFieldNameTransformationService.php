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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\QueryLanguage\PqlAdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CachedSearchIndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexEntityServiceInterface;

/**
 * @internal
 */
final readonly class SearchPqlFieldNameTransformationService implements SearchPqlFieldNameTransformationServiceInterface
{
    private const KEYWORD_SUFFIX = '.keyword';

    public function __construct(
        private IndexNameResolverInterface $indexNameResolver,
        private IndexEntityServiceInterface $indexEntityService,
        private CachedSearchIndexMappingServiceInterface $searchIndexMappingService,
        private PqlAdapterInterface $pqlAdapter,
    ) {
    }

    public function transformFieldnameForSearch(SearchInterface $search, string $fieldName, bool $sort = false): string
    {
        $indexEntity = $this->getIndexEntity($search);
        $indexMapping = $this->searchIndexMappingService->getMapping($indexEntity->getIndexName());

        return $this->pqlAdapter->transformFieldName(
            $fieldName,
            $indexMapping,
            $indexEntity,
            $sort
        );
    }

    public function isKeywordMultiField(SearchInterface $search, string $fieldName): bool
    {
        if (!str_ends_with($fieldName, self::KEYWORD_SUFFIX)) {
            return false;
        }

        $parentPath = substr($fieldName, 0, -strlen(self::KEYWORD_SUFFIX));
        $indexMapping = $this->searchIndexMappingService->getMapping($this->getIndexEntity($search)->getIndexName());
        foreach ($indexMapping as $mapping) {
            $parentMapping = $this->getPropertyMapping($parentPath, $mapping['mappings'] ?? []);
            // a multi-field is listed under "fields" of its parent, an object property under "properties"
            if (isset($parentMapping['fields']['keyword'])) {
                return true;
            }
        }

        return false;
    }

    private function getIndexEntity(SearchInterface $search): IndexEntity
    {
        return $this->indexEntityService->getByIndexName(
            $this->indexNameResolver->resolveIndexName($search)
        );
    }

    private function getPropertyMapping(string $fieldPath, array $mapping): ?array
    {
        foreach (explode('.', $fieldPath) as $field) {
            $mapping = $mapping['properties'][$field] ?? null;
            if (!is_array($mapping)) {
                return null;
            }
        }

        return $mapping;
    }
}
