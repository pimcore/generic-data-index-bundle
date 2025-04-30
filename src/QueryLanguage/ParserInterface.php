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

namespace Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage;

use Doctrine\Common\Lexer\Token;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\QueryLanguage\ParsingException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\ParseResult;

/**
 * @internal
 */
interface ParserInterface
{
    /**
     * @param Token[] $tokens
     */
    public function apply(string $query, array $tokens, array $indexMapping): ParserInterface;

    /**
     * @throws ParsingException
     */
    public function parse(): ParseResult;
}
