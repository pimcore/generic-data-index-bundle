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

namespace Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\Pql;

use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\ParseResultSubQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\LexerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ParserInterface;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ProcessorInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\QueryLanguage\PqlAdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CachedSearchIndexMappingServiceInterface;

/**
 * @internal
 */
final readonly class Processor implements ProcessorInterface
{
    public function __construct(
        private LexerInterface $lexer,
        private ParserInterface $parser,
        private PqlAdapterInterface $pqlAdapter,
        private CachedSearchIndexMappingServiceInterface $cachedSearchIndexMappingService,
    ) {
    }

    public function process(string $query, IndexEntity $indexEntity): array
    {

        $this->lexer->setQuery($query);
        $tokens = $this->lexer->getTokens();

        $parseResult = $this->parser
            ->apply($query, $tokens, $this->cachedSearchIndexMappingService->getMapping($indexEntity->getIndexName()))
            ->parse();

        $resultQuery = $parseResult->getQuery();

        $subQueryResults = $this->pqlAdapter->processSubQueries($this, $query, $parseResult->getSubQueries());

        if ($resultQuery instanceof ParseResultSubQuery) {
            return $this->pqlAdapter->transformSubQuery($resultQuery, $subQueryResults);
        }

        $pqlAdapter = $this->pqlAdapter;
        array_walk_recursive(
            $resultQuery,
            static function (&$value) use ($subQueryResults, $pqlAdapter) {
                if ($value instanceof ParseResultSubQuery) {
                    $value = $pqlAdapter->transformSubQuery($value, $subQueryResults);
                }
            }
        );

        return $resultQuery;
    }
}
