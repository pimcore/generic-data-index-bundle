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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\ClassIdsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\ParentIdFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\PathFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\TagFilter;

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
                    ConditionType::SHOULD->value => [
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
                    ConditionType::MUST_NOT->value => [
                        new TermFilter(
                            field: SystemField::FULL_PATH->getPath('keyword'),
                            term: $pathFilter->getPathWithoutTrailingSlash(),
                        ),
                    ],
                ])
            );
        }
    }

    #[AsSearchModifierHandler]
    public function handleTagFilter(TagFilter $tagFilter, SearchModifierContextInterface $context): void
    {
        $tagIds = $tagFilter->getTagIds();

        $boolQuery = new BoolQuery();

        if (!$tagFilter->isIncludeChildTags()) {
            foreach ($tagIds as $tagId) {
                $boolQuery->addCondition(
                    ConditionType::MUST->value,
                    [
                        new TermFilter(
                            field: SystemField::TAGS->getPath(),
                            term: $tagId,
                        ),
                    ]
                );
            }

            $context->getSearch()->addQuery(
                $boolQuery
            );

            return;
        }

        foreach ($tagIds as $tagId) {
            $subQuery = new BoolQuery();

            $subQuery->addCondition(
                ConditionType::SHOULD->value,
                [
                    new TermFilter(
                        field: SystemField::TAGS->getPath(),
                        term: $tagId,
                    ),
                    new TermFilter(
                        field: SystemField::PARENT_TAGS->getPath(),
                        term: $tagId,
                    ),
                    ]
            );

            $boolQuery->addCondition(
                ConditionType::MUST->value,
                [
                    $subQuery->toArray(true),
                ]
            );
        }

        $context->getSearch()->addQuery(
            $boolQuery
        );

    }

    #[AsSearchModifierHandler]
    public function handleClassesFilter(
        ClassIdsFilter $classesFilter,
        SearchModifierContextInterface $context
    ): void {
        if (empty($classesFilter->getClassIds())) {
            return;
        }

        $field = SystemField::CLASS_ID;
        if ($classesFilter->useClassName()) {
            $field = SystemField::CLASS_NAME;
        }

        $query = new TermsFilter(
            field: $field->getPath('keyword'),
            terms: $classesFilter->getClassIds()
        );

        if ($classesFilter->includeFolders()) {
            $query = new BoolQuery([
                ConditionType::SHOULD->value => [
                    new TermFilter(
                        field: SystemField::TYPE->getPath(),
                        term: 'folder'
                    ),
                    $query,
                ],
            ]);
        }

        $context->getSearch()->addQuery(
            new BoolQuery([ConditionType::MUST->value => [$query]])
        );
    }
}
