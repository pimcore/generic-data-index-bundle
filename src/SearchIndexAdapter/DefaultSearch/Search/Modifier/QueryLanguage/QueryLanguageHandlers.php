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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\QueryLanguage\ParsingException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\QueryLanguage\PqlFilter;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ProcessorInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\IndexNameResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexEntityServiceInterface;

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

        $query = $this->queryLanguageProcessor->process(
            $pql->getQuery(),
            $this->indexEntityService->getByIndexName(
                $this->indexNameResolver->resolveIndexName($context->getOriginalSearch())
            )
        );

        $context->getSearch()->addQuery(
            new BoolQuery([ConditionType::MUST->value => $query])
        );
    }
}
