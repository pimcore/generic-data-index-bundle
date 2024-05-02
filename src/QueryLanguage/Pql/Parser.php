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

use Doctrine\Common\Lexer\Token;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\QueryLanguage\ParsingException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\ParseResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\ParseResultSubQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ParserInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\QueryLanguage\PqlAdapterInterface;

/**
 * @internal
 */
final class Parser implements ParserInterface
{
    private const FIELD_NAME_TOKENS = [
        QueryTokenType::T_FIELDNAME,
        QueryTokenType::T_RELATION_FIELD,
    ];

    private const OPERATOR_TOKENS = [
        QueryTokenType::T_EQ,
        QueryTokenType::T_GT,
        QueryTokenType::T_LT,
        QueryTokenType::T_GTE,
        QueryTokenType::T_LTE,
        QueryTokenType::T_LIKE,
    ];

    private const NUMERIC_TOKENS = [
        QueryTokenType::T_INTEGER,
        QueryTokenType::T_FLOAT,
    ];

    private int $index = 0;

    public function __construct(
        private readonly PqlAdapterInterface $pqlAdapter,
        /** @var Token[] */
        private readonly array $tokens = [],
        private readonly ?IndexEntity $indexEntity = null,
        private readonly array $indexMapping = [],
    ) {
    }

    public function apply(array $tokens, IndexEntity $indexEntity, array $indexMapping): ParserInterface
    {
        return new Parser($this->pqlAdapter, $tokens, $indexEntity, $indexMapping);
    }

    private function currentToken(): ?Token
    {
        return $this->tokens[$this->index] ?? null;
    }

    private function advance(): void
    {
        ++$this->index;
    }

    /**
     * @throws ParsingException
     */
    private function validateCurrentTokenNotEmpty(): void
    {
        if ($this->currentToken() === null) {
            $this->throwParsingException('some token', 'end of input. Seems query is truncated');
        }
    }

    /**
     * @throws ParsingException
     */
    private function expectRightParenthesis(): void
    {
        $this->validateCurrentTokenNotEmpty();
        $token = $this->currentToken();
        if (!$token || !$token->isA(QueryTokenType::T_RPAREN)) {
            $this->throwParsingException(
                'token type `' . QueryTokenType::T_RPAREN->value . '`',
                '`' . ($token['type']->value ?? 'null') . '`'
            );
        }
        $this->advance();
    }

    /**
     * @throws ParsingException
     */
    private function parseCondition(array &$subQueries): array|ParseResultSubQuery
    {
        $expr = $this->parseExpression($subQueries);
        while ($token = $this->currentToken()) {
            $this->validateCurrentTokenNotEmpty(); // Ensure the loop hasn't encountered unexpected end of input
            if ($token->isA(QueryTokenType::T_AND, QueryTokenType::T_OR)) {
                $this->advance(); // Skip the logical operator
                $rightExpr = $this->parseExpression($subQueries);
                if ($token->isA(QueryTokenType::T_AND)) {
                    $expr = ['bool' => ['must' => [$expr, $rightExpr]]];
                } else {
                    $expr = ['bool' => ['should' => [$expr, $rightExpr], 'minimum_should_match' => 1]];
                }
            } else {
                break;
            }
        }

        return $expr;
    }

    /**
     * @throws ParsingException
     */
    private function parseExpression(array &$subQueries): array|ParseResultSubQuery
    {
        $this->validateCurrentTokenNotEmpty(); // Check before attempting to parse the expression
        $token = $this->currentToken();

        if ($token?->isA(QueryTokenType::T_LPAREN)) {
            $this->advance(); // Skip '('
            $expr = $this->parseCondition($subQueries);
            $this->expectRightParenthesis(); // Ensure ')' is present

            return $expr;
        }

        if ($token?->isA(QueryTokenType::T_QUERY_STRING)) {
            return $this->pqlAdapter->translateToQueryStringQuery($token->value);
        }

        return $this->parseComparison($subQueries);
    }

    /**
     * @throws ParsingException
     */
    private function parseComparison(array &$subQueries): array|ParseResultSubQuery
    {
        $this->validateCurrentTokenNotEmpty();

        if (!$this->currentToken() || !$this->currentToken()->isA(...self::FIELD_NAME_TOKENS)) {
            $this->throwParsingException('an field name', '`' . ($this->currentToken()['value'] ?? 'null') . '`');
        }

        $fieldType = $this->currentToken()['type'];
        $field = $this->currentToken()['value'];
        $this->advance(); // Move to operator
        $this->validateCurrentTokenNotEmpty();

        $operatorToken = $this->currentToken();

        if ($operatorToken === null || !$operatorToken->isA(...self::OPERATOR_TOKENS)) {
            $this->throwParsingException('a comparison operator', '`' . ($operatorToken['value'] ?? 'null') . '`');
        }

        $this->advance(); // Move to value
        $this->validateCurrentTokenNotEmpty();

        // Adjusting expectation for the value type to include both strings and numerics
        $valueToken = $this->currentToken();
        if (!$valueToken || !$valueToken->isA(QueryTokenType::T_STRING, ...self::NUMERIC_TOKENS)) {
            $this->throwParsingException('a string or numeric value', '`' . ($valueToken['value'] ?? 'null') . '`');
        }

        $this->advance(); // Prepare for next

        if($fieldType === QueryTokenType::T_RELATION_FIELD) {
            return $this->createSubQuery($subQueries, $field, $operatorToken, $valueToken);
        }

        $operatorTokenType = $operatorToken->type;
        if (!$operatorTokenType instanceof QueryTokenType) {
            $this->throwParsingException(QueryTokenType::class, get_debug_type($operatorTokenType));
        }

        $field = $this->pqlAdapter->transformFieldName($field, $this->indexEntity, $this->indexMapping);

        /** @var QueryTokenType $operatorTokenType */
        return $this->pqlAdapter->translateOperatorToSearchQuery($operatorTokenType, $field, $valueToken->value);
    }

    private function createSubQuery(
        array &$subQueries,
        string $field,
        Token $operatorToken,
        Token $valueToken
    ): ParseResultSubQuery {

        $subQueryId = uniqid('subquery_', true);
        $fieldParts = explode(':', $field);
        $relationFieldPath = $fieldParts[0];
        $relationFieldPath = $this->pqlAdapter->transformFieldName(
            $relationFieldPath,
            $this->indexEntity,
            $this->indexMapping
        );

        $targetPath = $fieldParts[1];
        $targetPathParts = explode('.', $targetPath);

        $targetType = array_shift($targetPathParts);
        $targetFieldname = implode('.', $targetPathParts);

        $value = $valueToken->value;
        if ($valueToken->type === QueryTokenType::T_STRING) {
            $value = '"' . $value . '"';
        }

        $subQuery = new ParseResultSubQuery(
            $subQueryId,
            $relationFieldPath,
            $targetType,
            $targetFieldname . ' ' . $operatorToken->value . ' ' . $value
        );

        $subQueries[$subQueryId] = $subQuery;

        return $subQuery;

    }

    /**
     * @throws ParsingException
     */
    public function parse(): ParseResult
    {

        $subQueries = [];
        $query = $this->parseCondition($subQueries);

        return new ParseResult($query, $subQueries);
    }

    /**
     * @throws ParsingException
     */
    private function throwParsingException(string $expected, string $found): void
    {
        $token = $this->currentToken();

        throw new ParsingException($expected, $found, $token);
    }
}
