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

namespace Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage;

use Doctrine\Common\Lexer\Token;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\QueryLanguage\ParsingException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\ParseResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;

/**
 * @internal
 */
interface ParserInterface
{
    /**
     * @param Token[] $tokens
     */
    public function apply(array $tokens, array $indexMapping): ParserInterface;

    /**
     * @throws ParsingException
     */
    public function parse(): ParseResult;
}
