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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\Modifier\QueryLanguage;

use Pimcore\Bundle\GenericDataIndexBundle\Attribute\OpenSearch\AsSearchModifierHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\Query;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\QueryLanguage\Pql;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ProcessorInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\IndexNameResolverInterface;

/**
 * @internal
 */
final readonly class QueryLanguageHandlers
{
    public function __construct(
        private ProcessorInterface $queryLanguageProcessor,
        private IndexNameResolverInterface $indexNameResolver,
    ) {
    }

    #[AsSearchModifierHandler]
    public function handlePql(
        Pql $pql,
        SearchModifierContextInterface $context
    ): void {

        $query = $this->queryLanguageProcessor->process(
            $pql->getQuery(),
            $this->indexNameResolver->resolveIndexName($context->getOriginalSearch())
        );

        $context->getSearch()->addQuery(
            Query::createFromArray(
                $query
            )
        );
    }
}
