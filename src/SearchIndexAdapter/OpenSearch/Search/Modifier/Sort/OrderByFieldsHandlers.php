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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\Sort;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Sort\FieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\OrderByField;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\QueryLanguage\PqlAdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\IndexNameResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CachedSearchIndexMappingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexEntityServiceInterface;

/**
 * @internal
 */
final class OrderByFieldsHandlers
{
    public function __construct(
        private IndexNameResolverInterface $indexNameResolver,
        private IndexEntityServiceInterface $indexEntityService,
        private CachedSearchIndexMappingServiceInterface $searchIndexMappingService,
        private PqlAdapterInterface $pqlAdapter,
    ) {
    }

    #[AsSearchModifierHandler]
    public function handleOrderByField(
        OrderByField $orderByField,
        SearchModifierContextInterface $context
    ): void {
        $fieldName = $orderByField->getFieldName();

        if ($orderByField->isPqlFieldNameResolutionEnabled()) {
            $indexEntity =  $this->indexEntityService->getByIndexName(
                $this->indexNameResolver->resolveIndexName($context->getOriginalSearch())
            );
            $indexMapping = $this->searchIndexMappingService->getMapping($indexEntity->getIndexName());
            $fieldName = $this->pqlAdapter->transformFieldName(
                $fieldName,
                $indexMapping,
                $indexEntity,
                true
            );
        }

        $context->getSearch()
            ->addSort(
                new FieldSort(
                    $fieldName,
                    $orderByField->getDirection()->value
                )
            );
    }
}
