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

    public function transformFieldnameForSearch(SearchInterface $search, string $fieldName, bool $sort = false): string
    {
        $indexEntity =  $this->indexEntityService->getByIndexName(
            $this->indexNameResolver->resolveIndexName($search)
        );
        $indexMapping = $this->searchIndexMappingService->getMapping($indexEntity->getIndexName());

        return $this->pqlAdapter->transformFieldName(
            $fieldName,
            $indexMapping,
            $indexEntity,
            $sort
        );
    }
}
