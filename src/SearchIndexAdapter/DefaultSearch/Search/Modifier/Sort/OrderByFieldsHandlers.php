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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\Sort;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\Search\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Sort\FieldSort;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\OrderByField;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchPqlFieldNameTransformationServiceInterface;

/**
 * @internal
 */
final class OrderByFieldsHandlers
{
    public function __construct(
        private SearchPqlFieldNameTransformationServiceInterface $fieldNameTransformationService,
    ) {
    }

    #[AsSearchModifierHandler]
    public function handleOrderByField(
        OrderByField $orderByField,
        SearchModifierContextInterface $context
    ): void {
        $fieldName = $orderByField->getFieldName();

        if ($orderByField->isPqlFieldNameResolutionEnabled()) {
            $fieldName = $this->fieldNameTransformationService->transformFieldnameForSearch(
                $context->getOriginalSearch(),
                $fieldName,
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
