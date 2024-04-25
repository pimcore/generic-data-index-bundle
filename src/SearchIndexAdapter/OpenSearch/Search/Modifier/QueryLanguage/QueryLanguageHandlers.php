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

/**
 * @internal
 */
final readonly class QueryLanguageHandlers
{
    public function __construct(
        private ProcessorInterface $queryLanguageProcessor
    ) {
    }

    #[AsSearchModifierHandler]
    public function handlePql(
        Pql $pql,
        SearchModifierContextInterface $context
    ): void {

        $context->getSearch()->addQuery(
            Query::createFromArray(
                $this->queryLanguageProcessor->process($pql->getQuery())
            )
        );
    }
}
