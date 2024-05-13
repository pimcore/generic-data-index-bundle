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
            'my_field = "foo"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
            ],
            'my_field LIKE "foo*"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_LIKE, 'value' => 'LIKE'],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo*'],
            ],
            'my_field >= 42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_GTE, 'value' => '>='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => '42'],
            ],
            'my_field <= 42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_LTE, 'value' => '<='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => '42'],
            ],
            'my_field > 42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_GT, 'value' => '>'],
                ['type' => QueryTokenType::T_INTEGER, 'value' => '42'],
            ],
            'my_field < 42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_LT, 'value' => '<'],
                ['type' => QueryTokenType::T_INTEGER, 'value' => '42'],
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
            'my_field = "foo"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
            ],
            'my_field = 42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => '42'],
            ],
            'my_field = 42.42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_FLOAT, 'value' => '42.42'],
            ],
        ];

        foreach ($testCases as $testCase => $expected) {
            $lexer->setQuery($testCase);
            $tokens = $lexer->getTokens();
            $this->assertTokens($expected, $tokens, $testCase);
        }
    }

    public function testGetTokensQueryString(): void
    {
        $lexer = new Lexer();

        $testCases = [
            'Query("standard_fields.color:(red or blue)")' => [
                ['type' => QueryTokenType::T_QUERY_STRING, 'value' => 'standard_fields.color:(red or blue)'],
            ],
            'price > 100 and Query("standard_fields.color:(red or blue)")' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'price'],
                ['type' => QueryTokenType::T_GT, 'value' => '>'],
                ['type' => QueryTokenType::T_INTEGER, 'value' => '100'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_QUERY_STRING, 'value' => 'standard_fields.color:(red or blue)'],
            ],
            'Query("standard_fields.color:(red or blue)") and age < 1970' => [
                ['type' => QueryTokenType::T_QUERY_STRING, 'value' => 'standard_fields.color:(red or blue)'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'age'],
                ['type' => QueryTokenType::T_LT, 'value' => '<'],
                ['type' => QueryTokenType::T_INTEGER, 'value' => '1970'],
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
            'my_field = "foo"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
            ],
            "my_field = 'foo'" => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
            ],
            '(my_field = "foo" or name = "bar")' => [
                ['type' => QueryTokenType::T_LPAREN, 'value' => '('],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_OR, 'value' => 'or'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
                ['type' => QueryTokenType::T_RPAREN, 'value' => ')'],
            ],
            'my_field = "foo" and name = "bar"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
            ],
            'my_field = "foo" and name = "bar" and age = 42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'age'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => '42'],
            ],
            'my_field = "foo" and name = "bar" and age = 42 and price = 42.42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'age'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => '42'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'price'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_FLOAT, 'value' => '42.42'],
            ],
            'my_field = "foo" and (name = "bar" or age = 42)' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_LPAREN, 'value' => '('],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
                ['type' => QueryTokenType::T_OR, 'value' => 'or'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'age'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => '42'],
                ['type' => QueryTokenType::T_RPAREN, 'value' => ')'],
            ],
            'my_field = "foo" and (name = "bar" or age = 42) and price = 42.42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_LPAREN, 'value' => '('],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
                ['type' => QueryTokenType::T_OR, 'value' => 'or'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'age'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => '42'],
                ['type' => QueryTokenType::T_RPAREN, 'value' => ')'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'price'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_FLOAT, 'value' => '42.42'],
            ],
            'my_field = "foo" and (name = "bar" or (age > 42 and price > 42.42))' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_LPAREN, 'value' => '('],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
                ['type' => QueryTokenType::T_OR, 'value' => 'or'],
                ['type' => QueryTokenType::T_LPAREN, 'value' => '('],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'age'],
                ['type' => QueryTokenType::T_GT, 'value' => '>'],
                ['type' => QueryTokenType::T_INTEGER, 'value' => '42'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'price'],
                ['type' => QueryTokenType::T_GT, 'value' => '>'],
                ['type' => QueryTokenType::T_FLOAT, 'value' => '42.42'],
                ['type' => QueryTokenType::T_RPAREN, 'value' => ')'],
                ['type' => QueryTokenType::T_RPAREN, 'value' => ')'],
            ],
            'my_field LIKE "foo"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_LIKE, 'value' => 'LIKE'],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
            ],
            'my_field LIKE "foo" and name = "bar"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_LIKE, 'value' => 'LIKE'],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo'],
                ['type' => QueryTokenType::T_AND, 'value' => 'and'],
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'name'],
                ['type' => QueryTokenType::T_EQ, 'value' => '='],
                ['type' => QueryTokenType::T_STRING, 'value' => 'bar'],
            ],
            'my_field LIKE "foo*"' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'my_field'],
                ['type' => QueryTokenType::T_LIKE, 'value' => 'LIKE'],
                ['type' => QueryTokenType::T_STRING, 'value' => 'foo*'],
            ],
            'age >= 42' => [
                ['type' => QueryTokenType::T_FIELDNAME, 'value' => 'age'],
                ['type' => QueryTokenType::T_GTE, 'value' => '>='],
                ['type' => QueryTokenType::T_INTEGER, 'value' => '42'],
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
