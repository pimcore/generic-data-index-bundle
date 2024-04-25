<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\Pql;

use Doctrine\Common\Lexer\AbstractLexer;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\LexerInterface;

//
//
//CONDITION = EXPRESSION | EXPRESSION ("AND" | "OR") EXPRESSION
//
//EXPRESSION = "(" CONDITION ")" | COMPARISON | QUERY_STRING_QUERY
//
//COMPARISON = FIELDNAME OPERATOR VALUE | RELATION_COMPARISON
//
//RELATION_COMPARISON = RELATION_FIELD_NAME OPERATOR VALUE
//
//FIELDNAME = IDENTIFIER{.IDENTIFIER}
//
//RELATION_FIELD_NAME = FIELDNAME:IDENTIFIER{.FIELDNAME}
//
//IDENTIFIER = [a-zA-Z_]\w*
//
//OPERATOR = "="|"<"|">"|">="|"<="|"LIKE"
//
//VALUE = INTEGER | FLOAT | "'" STRING "'" | '"' STRING '"'
//
//QUERY_STRING_QUERY = 'QUERY("' STRING '")'
//


class Lexer extends AbstractLexer implements LexerInterface
{
    private const REGEX_FIELD_NAME = '[a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)*';
    //const REGEX_RELATION_FIELD = '[a-zA-Z_]\w*(?:\:[a-zA-Z_]\w*)(?:\.[a-zA-Z_]\w*)*';
    private const REGEX_RELATION_FIELD = self::REGEX_FIELD_NAME . '(?:\:[a-zA-Z_]\w*)(?:\.[a-zA-Z_]\w*)+';
    private const REGEX_QUERY_STRING = 'query\(\"(?:.*?)\"\)';
    private const REGEX_NUMBERS = '(?:[0-9]+(?:[\.][0-9]+)*)(?:e[+-]?[0-9]+)?';
    private const REGEX_STRING_SINGLE_QUOTE = "'(?:[^']|'')*'";
    private const REGEX_STRING_DOUBLE_QUOTE = '"(?:[^"]|"")*"';
    /**
     * Lexical catchable patterns.
     */
    protected function getCatchablePatterns(): array
    {
        return [
            self::REGEX_QUERY_STRING,
            self::REGEX_RELATION_FIELD,
            self::REGEX_FIELD_NAME,
            self::REGEX_NUMBERS,
            self::REGEX_STRING_SINGLE_QUOTE,
            self::REGEX_STRING_DOUBLE_QUOTE

            //TODO add regex for operators
            //TODO add regex for ( )
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
    protected function getType(&$value): \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType
    {
        $tokenType = \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_NONE;

        // Check for specific words or characters to assign token types
        switch (true) {
            case is_numeric($value):
                $tokenType = is_int($value) ? \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_INTEGER : \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_FLOAT;
                break;
            case in_arrayi($value[0], ['"', "'"]):
                $value = substr($value, 1, -1);
                $value = str_replace(["''", '""'], ["'", '"'], $value);
                $tokenType = \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_STRING;
                break;
            case str_starts_with(strtolower($value), 'query("'):
                $value = substr($value, 7, -2);
                $tokenType = \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_QUERY_STRING;
                break;
            case $value === '(':
                $tokenType = \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_LPAREN;
                break;
            case $value === ')':
                $tokenType = \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_RPAREN;
                break;
            case strtolower($value) === 'and':
                $tokenType = \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_AND;
                break;
            case strtolower($value) === 'or':
                $tokenType = \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_OR;
                break;
            case $value === '=':
                $tokenType = \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_EQ;
                break;
            case $value === '>':
                $tokenType = \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_GT;
                break;
            case $value === '<':
                $tokenType = \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_LT;
                break;
            case $value === '>=':
                $tokenType = \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_GTE;
                break;
            case $value === '<=':
                $tokenType = \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_LTE;
                break;
            case strtolower($value) === 'like':
                $tokenType = \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_LIKE;
                break;
            case preg_match('#' . self::REGEX_RELATION_FIELD . '#', $value):
                $tokenType = \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_RELATION_FIELD;
                break;
            case preg_match('#' . self::REGEX_FIELD_NAME . '#', $value):
                $tokenType = \Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType::T_FIELDNAME;
                break;
        }

        return $tokenType;
    }

    public function getTokens(): array
    {
        $tokens = [];
        $this->moveNext();
        while ($this->lookahead !== null) {
            #p_r("Token: " . (string)$this->lookahead['type']->value . " - Value: " . $this->lookahead['value']);
            $tokens[] = $this->lookahead;
            $this->moveNext();
        }

        return $tokens;
    }

    public function setQuery(string $query): void
    {
        $this->setInput($query);
    }


}