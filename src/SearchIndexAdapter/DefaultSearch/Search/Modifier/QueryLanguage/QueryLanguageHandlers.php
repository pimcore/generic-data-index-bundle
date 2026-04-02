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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\QueryLanguage;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\Search\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\QueryLanguage\ParsingException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermsFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\QueryLanguage\PqlFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\QueryLanguage\TreePqlFilter;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ProcessorInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\IndexNameResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexEntityServiceInterface;
use Pimcore\Model\DataObject\AbstractObject;

/**
 * @internal
 */
final readonly class QueryLanguageHandlers
{
    public function __construct(
        private ProcessorInterface $queryLanguageProcessor,
        private IndexNameResolverInterface $indexNameResolver,
        private IndexEntityServiceInterface $indexEntityService,
    ) {
    }

    /**
     * @throws ParsingException
     */
    #[AsSearchModifierHandler]
    public function handlePqlFilter(
        PqlFilter $pql,
        SearchModifierContextInterface $context
    ): void {
        $context->getSearch()->addQuery(
            new BoolQuery([
                ConditionType::MUST->value => $this->processPql(
                    $pql->getQuery(),
                    $context,
                ),
            ])
        );
    }

    /**
     * @throws ParsingException
     */
    #[AsSearchModifierHandler]
    public function handleTreePqlFilter(
        TreePqlFilter $treePqlFilter,
        SearchModifierContextInterface $context
    ): void {
        $pqlConditions = $this->processPql($treePqlFilter->getQuery(), $context);
        $folderKeys = $treePqlFilter->getRelevantFolderKeys();

        // Branch B: non-folders matching the PQL query
        $nonFolderBranch = new BoolQuery([
            ConditionType::MUST_NOT->value => [
                new TermFilter(
                    field: SystemField::TYPE->getPath(),
                    term: AbstractObject::OBJECT_TYPE_FOLDER,
                ),
            ],
            ConditionType::MUST->value => $pqlConditions,
        ]);

        // If no relevant folders found, only show non-folder PQL matches
        if (empty($folderKeys)) {
            $context->getSearch()->addQuery(
                new BoolQuery([ConditionType::MUST->value => [$nonFolderBranch]])
            );

            return;
        }

        // Branch A: folders whose key is in the relevant folder list
        $folderBranch = new BoolQuery([
            ConditionType::FILTER->value => [
                new TermFilter(
                    field: SystemField::TYPE->getPath(),
                    term: AbstractObject::OBJECT_TYPE_FOLDER,
                ),
                new TermsFilter(
                    field: SystemField::KEY->getPath(),
                    terms: $folderKeys,
                ),
            ],
        ]);

        // Combine: at least one branch must match
        $combinedQuery = new BoolQuery([
            ConditionType::SHOULD->value => [
                $folderBranch,
                $nonFolderBranch,
            ],
        ]);

        $context->getSearch()->addQuery(
            new BoolQuery([ConditionType::MUST->value => [$combinedQuery]])
        );
    }

    /**
     * @throws ParsingException
     */
    private function processPql(
        string $query,
        SearchModifierContextInterface $context,
    ): array {
        return $this->queryLanguageProcessor->process(
            $query,
            $this->indexEntityService->getByIndexName(
                $this->indexNameResolver->resolveIndexName(
                    $context->getOriginalSearch()
                )
            )
        );
    }
}
