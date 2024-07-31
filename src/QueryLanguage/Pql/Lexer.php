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

namespace Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\Pql;

use Doctrine\Common\Lexer\AbstractLexer;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\LexerInterface;

/**
 * CONDITION = EXPRESSION | EXPRESSION ("AND" | "OR") EXPRESSION
 *
 * EXPRESSION = "(" CONDITION ")" | COMPARISON | QUERY_STRING_QUERY
 *
 * COMPARISON = FIELDNAME OPERATOR VALUE | RELATION_COMPARISON
 *
 * RELATION_COMPARISON = RELATION_FIELD_NAME OPERATOR VALUE
 *
 * FIELDNAME = IDENTIFIER{.IDENTIFIER}
 *
 * RELATION_FIELD_NAME = FIELDNAME:IDENTIFIER{.FIELDNAME}
 *
 * IDENTIFIER = [a-zA-Z_]\w*
 *
 * OPERATOR = "="|"!="|"<"|">"|">="|"<="|"LIKE"|"NOT LIKE"
 *
 * NULL = "NULL"
 *
 * VALUE = INTEGER | FLOAT | "'" STRING "'" | '"' STRING '"' | NULL
 *
 * QUERY_STRING_QUERY = 'QUERY("' STRING '")'
 */
class Lexer extends AbstractLexer implements LexerInterface
{
    private const REGEX_FIELD_NAME = '[a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)*';

    private const REGEX_RELATION_FIELD = self::REGEX_FIELD_NAME . '(?:\:[a-zA-Z_]\w*)(?:\.[a-zA-Z_]\w*)+';

    private const REGEX_QUERY_STRING = 'query\(\"(?:.*?)\"\)';

    private const REGEX_NUMBERS = '(?:[0-9]+(?:[\.][0-9]+)*)(?:e[+-]?[0-9]+)?';

    private const REGEX_STRING_SINGLE_QUOTE = "'(?:[^']|'')*'";

    private const REGEX_STRING_DOUBLE_QUOTE = '"(?:[^"]|"")*"';

    private const REGEX_NULL = 'null';

    private const REGEX_OPERATOR = '>=|<=|!=|=|>|<|not like|like';

    private const REGEX_PARANTHESES = '\(|\)';

    /**
     * Lexical catchable patterns.
     */
    protected function getCatchablePatterns(): array
    {
        return [
            self::REGEX_QUERY_STRING,
            self::REGEX_STRING_SINGLE_QUOTE,
            self::REGEX_STRING_DOUBLE_QUOTE,
            self::REGEX_OPERATOR,
            self::REGEX_NULL,
            self::REGEX_RELATION_FIELD,
            self::REGEX_FIELD_NAME,
            self::REGEX_NUMBERS,
            self::REGEX_PARANTHESES,
        ];
    }

    /**
     * Lexical non-catchable patterns.
     */
    protected function getNonCatchablePatterns(): array
    {
        return ['\s+', '(.)'];
    }

    /**
     * Retrieve token type. Also processes the token value if necessary.
     */
    protected function getType(&$value): QueryTokenType
    {
        // Check for specific words or characters to assign token types
        switch (true) {
            case is_numeric($value):
                $typeToken = $this->isIntegerString($value) ? QueryTokenType::T_INTEGER : QueryTokenType::T_FLOAT;

                break;
            case strlen($value)>1 && in_array($value[0], ["'", '"']) && $value[strlen($value)-1] === $value[0]:
                $value = substr($value, 1, -1);
                $value = str_replace(["''", '""'], ["'", '"'], $value);
                $typeToken = QueryTokenType::T_STRING;

                break;
            case strtolower($value) === 'null':
                $typeToken = QueryTokenType::T_NULL;
                break;
            case str_starts_with(strtolower($value), 'query("'):
                $value = substr($value, 7, -2);
                $typeToken = QueryTokenType::T_QUERY_STRING;

                break;
            case $value === '(':
                $typeToken = QueryTokenType::T_LPAREN;

                break;
            case $value === ')':
                $typeToken = QueryTokenType::T_RPAREN;

                break;
            case strtolower($value) === 'and':
                $typeToken = QueryTokenType::T_AND;

                break;
            case strtolower($value) === 'or':
                $typeToken = QueryTokenType::T_OR;

                break;
            case $value === '=':
                $typeToken = QueryTokenType::T_EQ;

                break;
            case $value === '!=':
                $typeToken = QueryTokenType::T_NEQ;

                break;
            case $value === '>':
                $typeToken = QueryTokenType::T_GT;

                break;
            case $value === '<':
                $typeToken = QueryTokenType::T_LT;

                break;
            case $value === '>=':
                $typeToken = QueryTokenType::T_GTE;

                break;
            case $value === '<=':
                $typeToken = QueryTokenType::T_LTE;

                break;
            case strtolower($value) === 'like':
                $typeToken = QueryTokenType::T_LIKE;

                break;
            case strtolower($value) === 'not like':
                $typeToken = QueryTokenType::T_NOT_LIKE;

                break;
            case preg_match('#' . self::REGEX_RELATION_FIELD . '#', $value):
                $typeToken = QueryTokenType::T_RELATION_FIELD;

                break;
            case preg_match('#' . self::REGEX_FIELD_NAME . '#', $value):
                $typeToken = QueryTokenType::T_FIELDNAME;

                break;
            default:
                $typeToken = QueryTokenType::T_NONE;
        }

        return $typeToken;
    }

    public function getTokens(): array
    {
        $tokens = [];
        $this->moveNext();
        while ($this->lookahead !== null) {
            $tokens[] = $this->lookahead;
            $this->moveNext();
        }

        return $tokens;
    }

    public function setQuery(string $query): void
    {
        $this->setInput($query);
    }

    private function isIntegerString(string $value): bool
    {
        return ctype_digit($value)
            || (strlen($value) > 1 && $value[0] === '-' && ctype_digit(substr($value, 1)));
    }
}
