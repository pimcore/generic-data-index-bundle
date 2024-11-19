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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\Filter;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\Search\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermsFilter;
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
    public function handleIntegerFilter(IntegerFilter $idFilter, SearchModifierContextInterface $context): void
    {
        $fieldName = $idFilter->getFieldName();
        if ($idFilter->isPqlFieldNameResolutionEnabled()) {
            $fieldName = $this->fieldNameTransformationService->transformFieldnameForSearch(
                $context->getOriginalSearch(),
                $fieldName
            );
        }

        $context->getSearch()->addQuery(
            new TermFilter(
                field: $fieldName,
                term: $idFilter->getSearchTerm(),
            )
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
