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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\BoolExistsQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\BooleanFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\ExcludeFoldersFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\ExcludeVariantsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IntegerFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\NumberFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchPqlFieldNameTransformationServiceInterface;
use Pimcore\Model\DataObject\AbstractObject;

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
            $this->getNumberQuery($integerFilter, null, $context->getOriginalSearch())
        );
    }

    #[AsSearchModifierHandler]
    public function handleNumberFilter(NumberFilter $numberFilter, SearchModifierContextInterface $context): void
    {
        $context->getSearch()->addQuery(
            $this->getNumberQuery($numberFilter, null, $context->getOriginalSearch())
        );
    }

    public function getNumberQuery(
        IntegerFilter|NumberFilter $filter,
        ?string $prefix = null,
        ?SearchInterface $search = null
    ): TermFilter {
        $fieldName = $filter->getFieldName();
        if ($prefix) {
            $fieldName = $prefix . '.' . $fieldName;
        }
        if ($search && $filter->isPqlFieldNameResolutionEnabled()) {
            $fieldName = $this->fieldNameTransformationService->transformFieldnameForSearch($search, $fieldName);
        }

        return new TermFilter(
            field: $fieldName,
            term: $filter->getSearchTerm(),
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
        $context->getSearch()->addQuery($this->excludeTypeQuery(AbstractObject::OBJECT_TYPE_FOLDER));
    }

    #[AsSearchModifierHandler]
    public function handleExcludeVariantsFilter(
        ExcludeVariantsFilter $excludeVariantsFilter,
        SearchModifierContextInterface $context
    ): void {
        $context->getSearch()->addQuery($this->excludeTypeQuery(AbstractObject::OBJECT_TYPE_VARIANT));
    }

    private function excludeTypeQuery(string $type): BoolQuery
    {
        return new BoolQuery([
            ConditionType::MUST_NOT->value => [
                new TermFilter(
                    field: SystemField::TYPE->getPath(),
                    term: $type,
                ),
            ],
        ]);
    }
}
