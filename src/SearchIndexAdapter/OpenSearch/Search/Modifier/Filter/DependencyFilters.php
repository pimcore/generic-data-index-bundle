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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\Filter;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\TermFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\TermsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Dependency\RequiredByFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Dependency\RequiresFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\Element\ElementSearchServiceInterface;

/**
 * @internal
 */
final readonly class DependencyFilters
{
    public function __construct(
        private ElementSearchServiceInterface $elementSearchService,
    ) {
    }

    #[AsSearchModifierHandler]
    public function handleRequiredByFilter(
        RequiredByFilter $requiredByFilter,
        SearchModifierContextInterface $context
    ): void {
        $context->getSearch()->addQuery(
            new TermFilter(
                field: SystemField::DEPENDENCIES->getPath($requiredByFilter->getElementType()->getShortValue()),
                term: $requiredByFilter->getId(),
            )
        );
    }

    #[AsSearchModifierHandler]
    public function handleRequiresFilter(
        RequiresFilter $requiresFilter,
        SearchModifierContextInterface $context
    ): void {
        $element = $this->elementSearchService->byId(
            $requiresFilter->getElementType(),
            $requiresFilter->getId(),
            $context->getOriginalSearch()->getUser()
        );

        $dependencies = SystemField::DEPENDENCIES->getData($element?->getSearchIndexData() ?? []);

        $boolQuery = new BoolQuery();
        foreach ($dependencies ?? [] as $elementType => $ids) {
            if (empty($ids)) {
                continue;
            }
            $boolQuery->addCondition(
                ConditionType::SHOULD->value,
                (new BoolQuery([
                    ConditionType::FILTER->value => [
                        new TermsFilter(
                            field: SystemField::ID->getPath(),
                            terms: $ids,
                        ),
                        new TermFilter(
                            field: SystemField::ELEMENT_TYPE->getPath(),
                            term: ElementType::fromShortValue($elementType)->value,
                        ),
                    ],
                ]))->toArray(true)
            );
        }

        if ($boolQuery->isEmpty()) {
            // do not deliver any results if there are no dependencies
            $context->getSearch()->addQuery(
                new TermFilter(
                    field: SystemField::ID->getPath(),
                    term: 0,
                )
            );

            return;
        }

        $context->getSearch()->addQuery(
            new BoolQuery([
                ConditionType::FILTER->value => $boolQuery->toArray(true),
            ])
        );
    }
}
