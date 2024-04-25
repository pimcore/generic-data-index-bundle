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

use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\LexerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ParserInterface;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ProcessorInterface;

/**
 * @internal
 */
final class Processor implements ProcessorInterface
{
    public function __construct(
        private readonly LexerInterface $lexer,
        private readonly ParserInterface $parser,
    ) {
    }

    public function process(string $query): array
    {
        $this->lexer->setQuery($query);
        $tokens = $this->lexer->getTokens();

        $this->parser->setTokens($tokens);
        $parseResult = $this->parser->parse();

        return $parseResult->getQuery();
    }
}
