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
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\ParseResult;

/**
 * @internal
 */
interface ParserInterface
{
    /**
     * @param Token[] $tokens
     */
    public function applyTokens(array $tokens): ParserInterface;

    public function parse(): ParseResult;
}
