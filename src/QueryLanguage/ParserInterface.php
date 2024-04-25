<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage;

use Doctrine\Common\Lexer\Token;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\ParseResult;

/**
 * @internal
 */
interface ParserInterface
{
    /**
     * @param Token[] $tokens
     */
    public function setTokens(array $tokens): void;
    public function parse(): ParseResult;
}