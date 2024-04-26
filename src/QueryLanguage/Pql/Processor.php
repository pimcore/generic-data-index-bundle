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

namespace Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\Pql;

use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\ParseResultSubQuery;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\LexerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ParserInterface;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ProcessorInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\QueryLanguage\PqlAdapterInterface;

/**
 * @internal
 */
final readonly class Processor implements ProcessorInterface
{
    public function __construct(
        private LexerInterface                    $lexer,
        private ParserInterface                   $parser,
        private PqlAdapterInterface               $pqlAdapter,
    ) {
    }

    public function process(string $query): array
    {

        $this->lexer->setQuery($query);
        $tokens = $this->lexer->getTokens();

        $parseResult = $this->parser
            ->applyTokens($tokens)
            ->parse();

        $result = $parseResult->getQuery();

        $subQueryResults = $this->pqlAdapter->processSubQueries($this, $parseResult->getSubQueries());

        $pqlAdapter = $this->pqlAdapter;
        array_walk_recursive(
            $result,
            static function (&$value) use($subQueryResults, $pqlAdapter) {
                if ($value instanceof ParseResultSubQuery)  {
                    $value = $pqlAdapter->transformSubQuery($value, $subQueryResults);
                }
            }
        );

        return $result;
    }


}
