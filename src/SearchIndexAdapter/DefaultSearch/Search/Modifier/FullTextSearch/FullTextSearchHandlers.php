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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\FullTextSearch;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\Search\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\WildcardFilterMode;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\MultiMatchFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\SimpleQueryStringFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\WildcardFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\ElementKeySearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\FullTextSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\MultiMatchSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\FullTextSearch\WildcardSearch;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchPqlFieldNameTransformationServiceInterface;

/**
 * @internal
 */
final readonly class FullTextSearchHandlers
{
    public function __construct(
        private SearchPqlFieldNameTransformationServiceInterface $fieldNameTransformationService,
    ) {
    }

    #[AsSearchModifierHandler]
    public function handleElementKeySearch(
        ElementKeySearch $elementKeySearch,
        SearchModifierContextInterface $context
    ): void {
        if (empty($elementKeySearch->getSearchTerm())) {
            return;
        }

        $context->getSearch()
            ->addQuery(
                new WildcardFilter(
                    SystemField::KEY->getPath(),
                    $elementKeySearch->getSearchTerm(),
                    WildcardFilterMode::SUFFIX
                )
            );
    }

    #[AsSearchModifierHandler]
    public function handleWildcardSearch(
        WildcardSearch $wildcardSearch,
        SearchModifierContextInterface $context
    ): void {
        $query = $this->getWildCardSubQuery($wildcardSearch, null, $context->getOriginalSearch());

        if ($query === null) {
            return;
        }

        $context->getSearch()->addQuery($query);
    }

    public function getWildCardSubQuery(
        WildcardSearch $wildcardSearch,
        ?string $prefix = null,
        ?SearchInterface $search = null
    ): ?WildcardFilter {
        if (empty($wildcardSearch->getSearchTerm())) {
            return null;
        }

        $fieldName = $wildcardSearch->getFieldName();
        if ($prefix) {
            $fieldName = $prefix . '.' . $fieldName;
        }

        if ($search && $wildcardSearch->isPqlFieldNameResolutionEnabled()) {
            $fieldName = $this->fieldNameTransformationService->transformFieldnameForSearch($search, $fieldName);
        }

        return new WildcardFilter($fieldName, $wildcardSearch->getSearchTerm(), WildcardFilterMode::BOTH);
    }

    #[AsSearchModifierHandler]
    public function handleFullTextSearch(
        FullTextSearch $fullValueSearch,
        SearchModifierContextInterface $context
    ): void {
        if (empty($fullValueSearch->getSearchTerm())) {
            return;
        }

        $context->getSearch()->addQuery(
            new SimpleQueryStringFilter(
                $fullValueSearch->getSearchTerm(),
                $fullValueSearch->getDefaultOperator(),
                $fullValueSearch->getFields(),
                $fullValueSearch->getFlags(),
            )
        );
    }

    #[AsSearchModifierHandler]
    public function handleMultiMatchSearch(
        MultiMatchSearch $multiMatchSearch,
        SearchModifierContextInterface $context
    ): void {
        if (empty($multiMatchSearch->getSearchTerm())) {
            return;
        }

        $context->getSearch()->addQuery(
            new MultiMatchFilter(
                $multiMatchSearch->getSearchTerm(),
                $multiMatchSearch->getFields(),
                $multiMatchSearch->getMatchType(),
                $multiMatchSearch->getOperator()
            )
        );
    }
}
