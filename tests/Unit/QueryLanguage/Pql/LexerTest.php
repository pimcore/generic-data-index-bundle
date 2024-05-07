<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\QueryLanguage\Pql;

use Codeception\Test\Unit;
use Doctrine\Common\Lexer\Token;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\Pql\Lexer;

/**
 * @internal
 */
final class LexerTest extends Unit
{
    public function testGetTokensOperators(): void
    {
        $lexer = new Lexer();

        $testCases = [
            'standard_fields.series = "foo"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
            ],
            'standard_fields.series LIKE "foo*"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_LIKE, 'value' => 'LIKE'],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo*'],
            ],
            'standard_fields.series >= 42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_GTE, 'value' => '>='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => 42],
            ],
            'standard_fields.series <= 42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_LTE, 'value' => '<='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => 42],
            ],
            'standard_fields.series > 42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_GT, 'value' => '>'],
                ['type' => QueryTokenType::T_INTEGER, 'value' => 42],
            ],
            'standard_fields.series < 42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_LT, 'value' => '<'],
                ['type' => QueryTokenType::T_INTEGER, 'value' => 42],
            ],
        ];

        foreach ($testCases as $testCase => $expected) {
            $lexer->setQuery($testCase);
            $tokens = $lexer->getTokens();
            $this->assertTokens($expected, $tokens, $testCase);
        }
    }
    public function testGetTokensValueTypes(): void
    {
        $lexer = new Lexer();

        $testCases = [
            'standard_fields.series = "foo"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
            ],
            'standard_fields.series = 42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => 42],
            ],
            'standard_fields.series = 42.42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_FLOAT, 'value' => 42.42],
            ],
        ];

        foreach ($testCases as $testCase => $expected) {
            $lexer->setQuery($testCase);
            $tokens = $lexer->getTokens();
            $this->assertTokens($expected, $tokens, $testCase);
        }
    }

    public function testGetTokensCombined(): void
    {
        $lexer = new Lexer();

        $testCases = [
            'standard_fields.series = "foo"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
            ],
            "standard_fields.series = 'foo'" => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
            ],
            '(standard_fields.series = "foo" or standard_fields.name = "bar")' => [
                ['type' => QueryTokenType::T_LPAREN, 'value' => '('],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_OR, 'value' => 'or'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
                ['type' => QueryTokenType::T_RPAREN, 'value' => ')'],
            ],
            'standard_fields.series = "foo" and standard_fields.name = "bar"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
            ],
            'standard_fields.series = "foo" and standard_fields.name = "bar" and standard_fields.age = 42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.age'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => 42],
            ],
            'standard_fields.series = "foo" and standard_fields.name = "bar" and standard_fields.age = 42 and standard_fields.price = 42.42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.age'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => 42],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.price'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_FLOAT, 'value' => 42.42],
            ],
            'standard_fields.series = "foo" and (standard_fields.name = "bar" or standard_fields.age = 42)' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_LPAREN, 'value' => '('],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
                ['type' => QueryTokenType::T_OR, 'value' => 'or'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.age'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => 42],
                ['type' => QueryTokenType::T_RPAREN, 'value' => ')'],
            ],
            'standard_fields.series = "foo" and (standard_fields.name = "bar" or standard_fields.age = 42) and standard_fields.price = 42.42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_LPAREN, 'value' => '('],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
                ['type' => QueryTokenType::T_OR, 'value' => 'or'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.age'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => 42],
                ['type' => QueryTokenType::T_RPAREN, 'value' => ')'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.price'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_FLOAT, 'value' => 42.42],
            ],
            'standard_fields.series = "foo" and (standard_fields.name = "bar" or (standard_fields.age > 42 and standard_fields.price > 42.42))' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_LPAREN, 'value' => '('],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
                ['type' => QueryTokenType::T_OR, 'value' => 'or'],
                ['type' => QueryTokenType::T_LPAREN, 'value' => '('],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.age'],
                ['type' => QueryTokenType::T_GT, 'value' => '>'],
                ['type' => QueryTokenType::T_INTEGER, 'value' => 42],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.price'],
                ['type' => QueryTokenType::T_GT, 'value' => '>'],
                ['type' => QueryTokenType::T_FLOAT, 'value' => 42.42],
                ['type' => QueryTokenType::T_RPAREN, 'value' => ')'],
                ['type' => QueryTokenType::T_RPAREN, 'value' => ')'],
            ],
            'standard_fields.series LIKE "foo"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_LIKE, 'value' => 'LIKE'],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
            ],
            'standard_fields.series LIKE "foo" and standard_fields.name = "bar"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_LIKE, 'value' => 'LIKE'],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
            ],
            'standard_fields.series LIKE "foo*"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.series'],
                ['type' => QueryTokenType::T_LIKE, 'value' => 'LIKE'],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo*'],
            ],
            'standard_fields.age >= 42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'standard_fields.age'],
                ['type' => QueryTokenType::T_GTE, 'value' => '>='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => 42],
            ],
        ];

        foreach ($testCases as $testCase => $expected) {
            $lexer->setQuery($testCase);
            $tokens = $lexer->getTokens();
            $this->assertTokens($expected, $tokens, $testCase);
        }
    }

    /**
     * @param Token[] $tokens
     */
    private function assertTokens(array $expected, array $tokens, string $query): void
    {
        $this->assertCount(count($expected), $tokens, $query);

        foreach ($expected as $index => $expect) {
            $this->assertSame($expect['type'], $tokens[$index]->type, $query);
            $this->assertSame($expect['value'], $tokens[$index]->value, $query);
        }
    }
}