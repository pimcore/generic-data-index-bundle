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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\QueryLanguage;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\QueryLanguage\ParsingException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery;
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
