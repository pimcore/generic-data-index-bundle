<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\Filter;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\TermFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\ParentIdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\PathFilter;

/**
 * @internal
 */
final class TreeFilters
{
    #[AsSearchModifierHandler]
    public function handleParentIdFilter(ParentIdFilter $parentIdFilter, SearchModifierContextInterface $context): void
    {
        $context->getSearch()->addQuery(
            new TermFilter(
                field: SystemField::PARENT_ID->getPath(),
                term: $parentIdFilter->getParentId(),
            )
        );
    }

    #[AsSearchModifierHandler]
    public function handlePathFilter(PathFilter $pathFilter, SearchModifierContextInterface $context): void
    {
        if ($pathFilter->isDirectChildrenOnly()) {

            $directChildrenFilter = new TermFilter(
                field: SystemField::PATH->getPath('keyword'),
                term: $pathFilter->getPathWithTrailingSlash(),
            );

            if (!$pathFilter->isIncludeParentItem()) {
                $context->getSearch()->addQuery($directChildrenFilter);

                return;
            }

            $context->getSearch()->addQuery(
                new BoolQuery([
                    'should' => [
                        $directChildrenFilter,
                        new TermFilter(
                            field: SystemField::FULL_PATH->getPath('keyword'),
                            term: $pathFilter->getPathWithoutTrailingSlash(),
                        ),
                    ],
                ])
            );

            return;
        }

        // when filtering for '/' we don't need to add any filter
        if ($pathFilter->getPath() === '/') {
            return;
        }

        $context->getSearch()->addQuery(
            new TermFilter(
                field: SystemField::FULL_PATH->getPath(),
                term: $pathFilter->getPathWithoutTrailingSlash(),
            )
        );

        if (!$pathFilter->isIncludeParentItem()) {
            $context->getSearch()->addQuery(
                new BoolQuery([
                    'must_not' => [
                        new TermFilter(
                            field: SystemField::FULL_PATH->getPath('keyword'),
                            term: $pathFilter->getPathWithoutTrailingSlash(),
                        ),
                    ],
                ])
            );
        }
    }
}
