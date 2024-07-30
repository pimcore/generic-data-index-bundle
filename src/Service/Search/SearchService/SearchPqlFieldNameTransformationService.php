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
