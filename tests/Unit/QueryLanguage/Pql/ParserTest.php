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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\QueryLanguage\ParsingException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\ParseResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\ParseResultSubQuery;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\Pql\Lexer;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\Pql\Parser;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\PqlAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\SubQueriesProcessorInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\ElementServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexEntityService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final class ParserTest extends Unit
{
    public function testParseComparison(): void
    {
        $this->assertQueryResult(
            'color = "red"',
            [
                'match' => ['color' => 'red'],
            ]
        );

        $this->assertQueryResult(
            'price > 27',
            [
                'range' => ['price' => ['gt' => 27]],
            ]
        );

        $this->assertQueryResult(
            'price < 30',
            [
                'range' => ['price' => ['lt' => 30]],
            ]
        );

        $this->assertQueryResult(
            'price >= 27',
            [
                'range' => ['price' => ['gte' => 27]],
            ]
        );

        $this->assertQueryResult(
            'price <= 30',
            [
                'range' => ['price' => ['lte' => 30]],
            ]
        );

        $this->assertQueryResult(
            'name like "Jaguar*"',
            [
                'wildcard' => ['name' => ['value' => 'Jaguar*', 'case_insensitive' => true]],
            ]
        );

        $this->assertQueryResult(
            'name like "Jag*ar*"',
            [
                'wildcard' => ['name' => ['value' => 'Jag*ar*', 'case_insensitive' => true]],
            ]
        );
        $this->assertQueryResult(
            'name like "Jag?ar"',
            [
                'wildcard' => ['name' => ['value' => 'Jag?ar', 'case_insensitive' => true]],
            ]
        );

        $this->assertQueryResult(
            'name like "Jaguar"',
            [
                'wildcard' => ['name' => ['value' => 'Jaguar', 'case_insensitive' => true]],
            ]
        );
    }

    public function testParseCondition(): void
    {
        $this->assertQueryResult(
            'color = "red" or series = "E-Type"',
            [
                'bool' => [
                    'should' => [
                        ['match' => ['color' => 'red']],
                        ['match' => ['series' => 'E-Type']],
                    ],
                    'minimum_should_match' => 1,
                ],
            ]
        );

        $this->assertQueryResult(
            'color = "red" and series = "E-Type"',
            [
                'bool' => [
                    'must' => [
                        ['match' => ['color' => 'red']],
                        ['match' => ['series' => 'E-Type']],
                    ],
                ],
            ]
        );
    }

    public function testParseExpression(): void
    {

        $this->assertQueryResult(
            '(color = "red" or series = "E-Type")',
            [
                'bool' => [
                    'should' => [
                        ['match' => ['color' => 'red']],
                        ['match' => ['series' => 'E-Type']],
                    ],
                    'minimum_should_match' => 1,
                ],
            ]
        );

        $this->assertQueryResult(
            '(color = "red" or series = "E-Type") and name = "Jaguar"',

            [
                'bool' => [
                    'must' => [
                        [
                            'bool' => [
                                'should' => [
                                    ['match' => ['color' => 'red']],
                                    ['match' => ['series' => 'E-Type']],
                                ],
                                'minimum_should_match' => 1,
                            ],
                        ],
                        ['match' => ['name' => 'Jaguar']],
                    ],
                ],
            ]
        );
        $this->assertQueryResult(
            'color = "red" or series = "E-Type" and name = "Jaguar"',

            [
                'bool' => [
                    'must' => [
                        [
                            'bool' => [
                                'should' => [
                                    ['match' => ['color' => 'red']],
                                    ['match' => ['series' => 'E-Type']],
                                ],
                                'minimum_should_match' => 1,
                            ],
                        ],
                        ['match' => ['name' => 'Jaguar']],
                    ],
                ],
            ]
        );

        $this->assertQueryResult(
            'color = "red" or (series = "E-Type" and name = "Jaguar")',
            [
                'bool' => [
                    'should' => [
                        ['match' => ['color' => 'red']],
                        [
                            'bool' => [
                                'must' => [
                                    ['match' => ['series' => 'E-Type']],
                                    ['match' => ['name' => 'Jaguar']],
                                ],
                            ],
                        ],
                    ],
                    'minimum_should_match' => 1,
                ],
            ]
        );

        $this->assertQueryResult(
            'color = "red" or ((series = "E-Type" and name = "Jaguar") or price > 100)',
            [
                'bool' => [
                    'should' => [
                        ['match' => ['color' => 'red']],
                        [
                            'bool' => [
                                'should' => [
                                    [
                                        'bool' => [
                                            'must' => [
                                                ['match' => ['series' => 'E-Type']],
                                                ['match' => ['name' => 'Jaguar']],
                                            ],
                                        ],
                                    ],
                                    ['range' => ['price' => ['gt' => 100]]],
                                ],
                                'minimum_should_match' => 1,
                            ],
                        ],
                    ],
                    'minimum_should_match' => 1,
                ],
            ]
        );
    }

    public function testQueryString(): void
    {
        $this->assertQueryResult(
            'Query("color:(red or blue)")',
            [
                'query_string' => [
                    'query' => 'color:(red or blue)',
                ],
            ]
        );

        $this->assertQueryResult(
            'series="Jaguar" and Query("color:(red or blue)")',
            [
                'bool' => [
                    'must' => [
                        ['match' => ['series' => 'Jaguar']],
                        [
                            'query_string' => [
                                'query' => 'color:(red or blue)',
                            ],
                        ],
                    ],
                ],
            ]
        );

        $this->assertQueryResult(
            '(Query("color:(red or blue)") and price>1000.23)',
            [
                'bool' => [
                    'must' => [
                        [
                            'query_string' => [
                                'query' => 'color:(red or blue)',
                            ],
                        ],
                        ['range' => ['price' => ['gt' => 1000.23]]],
                    ],
                ],
            ]
        );
    }

    public function testCreateSubQuery(): void
    {
        $this->assertSubQueryResult(
            'manufactorer:Manufactorer.name = "Jaguar"',
            [
                'relationFieldPath' => 'manufactorer',
                'targetType' => 'Manufactorer',
                'targetQuery' => 'name = "Jaguar"',
            ]
        );

        $this->assertSubQueryResult(
            'mainImage:Asset.id > 17',
            [
                'relationFieldPath' => 'mainImage',
                'targetType' => 'Asset',
                'targetQuery' => 'id > 17',
            ]
        );

        $this->assertSubQueriesResult(
            'manufactorer:Manufactorer.name = "Jaguar" or mainImage:Asset.id > 17',
            [
                'bool' => [
                    'should' => [
                        [
                            'relationFieldPath' => 'manufactorer',
                            'targetType' => 'Manufactorer',
                            'targetQuery' => 'name = "Jaguar"',
                        ],
                        [
                            'relationFieldPath' => 'mainImage',
                            'targetType' => 'Asset',
                            'targetQuery' => 'id > 17',
                        ],
                    ],
                    'minimum_should_match' => 1,
                ],
            ],
            [
                [
                    'relationFieldPath' => 'manufactorer',
                    'targetType' => 'Manufactorer',
                    'targetQuery' => 'name = "Jaguar"',
                ],
                [
                    'relationFieldPath' => 'mainImage',
                    'targetType' => 'Asset',
                    'targetQuery' => 'id > 17',
                ],
            ]
        );

        $this->assertSubQueriesResult(
            'age < 1980 and ((manufactorer:Manufactorer.name = "Jaguar" or age < 1970) and mainImage:Asset.id > 17)',
            [
                'bool' => [
                    'must' => [
                        ['range' => ['age' => ['lt' => 1980]]],
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'bool' => [
                                            'should' => [
                                                [
                                                    'relationFieldPath' => 'manufactorer',
                                                    'targetType' => 'Manufactorer',
                                                    'targetQuery' => 'name = "Jaguar"',
                                                ],
                                                ['range' => ['age' => ['lt' => 1970]]],
                                            ],
                                            'minimum_should_match' => 1,
                                        ],
                                    ],
                                    [
                                        'relationFieldPath' => 'mainImage',
                                        'targetType' => 'Asset',
                                        'targetQuery' => 'id > 17',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                [
                    'relationFieldPath' => 'manufactorer',
                    'targetType' => 'Manufactorer',
                    'targetQuery' => 'name = "Jaguar"',
                ],
                [
                    'relationFieldPath' => 'mainImage',
                    'targetType' => 'Asset',
                    'targetQuery' => 'id > 17',
                ],
            ]
        );
    }

    public function testParseError1(): void
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('end of input. Seems query is truncated');
        $this->parseQuery('color = "red" and');
    }

    public function testParseError2(): void
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Expected a field name, found `or`');
        $this->parseQuery('color = "red" and or');
    }

    public function testParseError3(): void
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('end of input. Seems query is truncated');
        $this->parseQuery('color = "red" and (age < 1970 or series = "E-Type"');
    }

    public function testParseError4(): void
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Expected a string, numeric value or null, found `red`');
        $this->parseQuery('color = red');
    }

    public function testParseError5(): void
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Expected a string, numeric value or null, found `(`');
        $this->parseQuery('color = (Color.name = red)');
    }

    public function testParseError6(): void
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Expected a comparison operator, found `:`');
        $this->parseQuery('manufacturer:Manufactorer = "Jaguar"');
    }

    public function testParseError7(): void
    {
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Expected a string, numeric value or null, found `"`');
        $this->parseQuery('manufacturer:Manufactorer.name = "Jaguar');
    }

    private function parseQuery(string $query): void
    {
        $parser = $this->createParser();
        $lexer = new Lexer();
        $lexer->setQuery($query);
        $tokens = $lexer->getTokens();
        $parser = $parser->apply($query, $tokens, []);
        $parser->parse();
    }

    private function assertQueryResult(string $query, array $result): void
    {
        $parser = $this->createParser();
        $lexer = new Lexer();
        $lexer->setQuery($query);
        $tokens = $lexer->getTokens();
        $parser = $parser->apply($query, $tokens, []);
        $parseResult = $parser->parse();
        $this->assertSame($result, $parseResult->getQuery());
        $this->assertEmpty($parseResult->getSubQueries());
    }

    private function assertSubQueryResult(string $query, array $subQuery): void
    {
        $parser = $this->createParser();
        $lexer = new Lexer();
        $lexer->setQuery($query);
        $tokens = $lexer->getTokens();
        $parser = $parser->apply($query, $tokens, []);
        $parseResult = $parser->parse();
        $this->assertSame($this->subQueryToArray($parseResult->getQuery()), $subQuery);
        $this->assertSame($this->subQueriesToArray($parseResult), [$subQuery]);
    }

    private function assertSubQueriesResult(string $queryString, array $query, array $subQueries): void
    {
        $parser = $this->createParser();
        $lexer = new Lexer();
        $lexer->setQuery($queryString);
        $tokens = $lexer->getTokens();
        $parser = $parser->apply($queryString, $tokens, []);
        $parseResult = $parser->parse();
        $resultQuery = $parseResult->getQuery();
        array_walk_recursive(
            $resultQuery,
            function (&$value) {
                if ($value instanceof ParseResultSubQuery) {
                    $value = $this->subQueryToArray($value);
                }
            }
        );
        $this->assertSame($query, $resultQuery);
        $this->assertSame($subQueries, $this->subQueriesToArray($parseResult));
    }

    private function subQueriesToArray(ParseResult $parseResult): array
    {
        return array_values(array_map(
            fn (ParseResultSubQuery $query) => $this->subQueryToArray($query),
            $parseResult->getSubQueries()
        ));
    }

    private function subQueryToArray(ParseResultSubQuery $query): array
    {
        return [
            'relationFieldPath' => $query->getRelationFieldPath(),
            'targetType' => $query->getTargetType(),
            'targetQuery' => $query->getTargetQuery(),
        ];
    }

    private function createParser(): Parser
    {
        $indexEntityService = new IndexEntityService(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(ElementServiceInterface::class),
        );

        $pqlAdapter = new PqlAdapter(
            $this->makeEmpty(SubQueriesProcessorInterface::class),
            [],
            [],
            []
        );

        return new Parser(
            $pqlAdapter,
            $indexEntityService,
        );
    }
}
