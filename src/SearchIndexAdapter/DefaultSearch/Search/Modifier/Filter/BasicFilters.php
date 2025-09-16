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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\Filter;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\Search\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\BoolExistsQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\BooleanFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\ExcludeFoldersFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IntegerFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchPqlFieldNameTransformationServiceInterface;

/**
 * @internal
 */
final readonly class BasicFilters
{
    public function __construct(
        private SearchPqlFieldNameTransformationServiceInterface $fieldNameTransformationService,
    ) {
    }

    #[AsSearchModifierHandler]
    public function handleIdFilter(IdFilter $idFilter, SearchModifierContextInterface $context): void
    {
        $context->getSearch()->addQuery(
            new TermFilter(
                field: SystemField::ID->getPath(),
                term: $idFilter->getId(),
            )
        );
    }

    #[AsSearchModifierHandler]
    public function handleIntegerFilter(IntegerFilter $integerFilter, SearchModifierContextInterface $context): void
    {
        $context->getSearch()->addQuery(
            $this->getIntegerQuery($integerFilter, null, $context->getOriginalSearch())
        );
    }

    public function getIntegerQuery(
        IntegerFilter $integerFilter,
        ?string $prefix = null,
        ?SearchInterface $search = null
    ): TermFilter{
        $fieldName = $integerFilter->getFieldName();
        if ($prefix) {
            $fieldName = $prefix . '.' . $fieldName;
        }
        if ($search && $integerFilter->isPqlFieldNameResolutionEnabled()) {
            $fieldName = $this->fieldNameTransformationService->transformFieldnameForSearch($search, $fieldName);
        }

        return new TermFilter(
            field: $fieldName,
            term: $integerFilter->getSearchTerm(),
        );
    }

    #[AsSearchModifierHandler]
    public function handleBooleanFilter(BooleanFilter $booleanFilter, SearchModifierContextInterface $context): void
    {
        $context->getSearch()->addQuery(
            $this->getBooleanQuery($booleanFilter, null, $context->getOriginalSearch())
        );
    }

    public function getBooleanQuery(
        BooleanFilter $booleanFilter,
        ?string $prefix = null,
        ?SearchInterface $search = null
    ): BoolExistsQuery|TermFilter {
        $fieldName = $booleanFilter->getFieldName();
        if ($prefix) {
            $fieldName = $prefix . '.' . $fieldName;
        }
        if ($search && $booleanFilter->isPqlFieldNameResolutionEnabled()) {
            $fieldName = $this->fieldNameTransformationService->transformFieldnameForSearch($search, $fieldName);
        }

        if ($booleanFilter->getSearchTerm() !== null) {
            return new TermFilter(
                field: $fieldName,
                term: $booleanFilter->getSearchTerm(),
            );
        }

        return new BoolExistsQuery(
            field: $fieldName,
        );
    }

    #[AsSearchModifierHandler]
    public function handleIdsFilter(IdsFilter $idsFilter, SearchModifierContextInterface $context): void
    {
        $context->getSearch()->addQuery(
            new TermsFilter(
                field: SystemField::ID->getPath(),
                terms: $idsFilter->getIds(),
            )
        );
    }

    #[AsSearchModifierHandler]
    public function handleExcludeFoldersFilter(
        ExcludeFoldersFilter $excludeFoldersFilter,
        SearchModifierContextInterface $context
    ): void {
        $context->getSearch()->addQuery(new BoolQuery([
            'must_not' => [
                new TermFilter(
                    field: SystemField::TYPE->getPath(),
                    term: 'folder',
                ),
            ],
        ]));
    }
}
