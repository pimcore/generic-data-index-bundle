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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\DefaultSearch\Query;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\MultiBoolQuery;

/**
 * @internal
 */
final class MultiBoolQueryTest extends Unit
{
    public function testConstructorAndGetters(): void
    {
        $field = 'status';
        $terms = [true, false];

        $multiBoolQuery = new MultiBoolQuery($field, $terms);

        self::assertSame($field, $multiBoolQuery->getField());
        self::assertSame($terms, $multiBoolQuery->getTerms());
    }

    public function testToArray(): void
    {
        $field = 'active';
        $terms = [true, false];

        $multiBoolQuery = new MultiBoolQuery($field, $terms);

        $expected = [
            'bool' => [
                'filter' => [
                    'bool' => [
                        'should' => [
                            [
                                'bool' => [
                                    'must_not' => [
                                        'exists' => ['field' => $field],
                                    ],
                                ],
                            ],
                            [
                                'terms' => [$field => $terms],
                            ],
                        ],
                        'minimum_should_match' => 1,
                    ],
                ],
            ],
        ];

        self::assertSame($expected, $multiBoolQuery->toArray(true));
    }

    public function testToArrayWithoutBool(): void
    {
        $field = 'published';
        $terms = [true];

        $multiBoolQuery = new MultiBoolQuery($field, $terms);

        $expected = [
            'filter' => [
                'bool' => [
                    'should' => [
                        [
                            'bool' => [
                                'must_not' => [
                                    'exists' => ['field' => $field],
                                ],
                            ],
                        ],
                        [
                            'terms' => [$field => $terms],
                        ],
                    ],
                    'minimum_should_match' => 1,
                ],
            ],
        ];

        self::assertSame($expected, $multiBoolQuery->toArray());
    }

    public function testToArrayAsSubQuery(): void
    {
        $field = 'enabled';
        $terms = [false];

        $multiBoolQuery = new MultiBoolQuery($field, $terms);
        $expected = [
            'bool' => [
                'should' => [
                    [
                        'bool' => [
                            'must_not' => [
                                'exists' => ['field' => $field],
                            ],
                        ],
                    ],
                    [
                        'terms' => [$field => $terms],
                    ],
                ],
                'minimum_should_match' => 1,
            ],
        ];

        self::assertSame($expected, $multiBoolQuery->toArrayAsSubQuery());
    }

    public function testWithMultipleTerms(): void
    {
        $field = 'visibility';
        $terms = [true, false, null];

        $multiBoolQuery = new MultiBoolQuery($field, $terms);

        self::assertSame($field, $multiBoolQuery->getField());
        self::assertSame($terms, $multiBoolQuery->getTerms());

        $result = $multiBoolQuery->toArray(true);
        self::assertArrayHasKey('bool', $result);
        self::assertArrayHasKey('filter', $result['bool']);
        self::assertArrayHasKey('bool', $result['bool']['filter']);
        self::assertArrayHasKey('should', $result['bool']['filter']['bool']);
        self::assertCount(2, $result['bool']['filter']['bool']['should']);
        self::assertSame(1, $result['bool']['filter']['bool']['minimum_should_match']);
    }

    public function testWithSingleTerm(): void
    {
        $field = 'is_active';
        $terms = [true];

        $multiBoolQuery = new MultiBoolQuery($field, $terms);

        $result = $multiBoolQuery->toArray();
        self::assertArrayHasKey('filter', $result);
        self::assertArrayHasKey('bool', $result['filter']);
        self::assertArrayHasKey('should', $result['filter']['bool']);

        // Should contain BoolExistsQuery and TermsFilter
        $shouldConditions = $result['filter']['bool']['should'];
        self::assertCount(2, $shouldConditions);

        // The first condition should be BoolExistsQuery (must_not exists)
        self::assertArrayHasKey('bool', $shouldConditions[0]);
        self::assertArrayHasKey('must_not', $shouldConditions[0]['bool']);

        // The second condition should be TermsFilter
        self::assertArrayHasKey('terms', $shouldConditions[1]);
        self::assertSame($terms, $shouldConditions[1]['terms'][$field]);
    }

    public function testWithStringField(): void
    {
        $field = 'category.subcategory.name';
        $terms = [true];

        $multiBoolQuery = new MultiBoolQuery($field, $terms);

        self::assertSame($field, $multiBoolQuery->getField());

        $result = $multiBoolQuery->toArrayAsSubQuery();
        $shouldConditions = $result['bool']['should'];

        // Verify field is used correctly in both conditions
        self::assertSame($field, $shouldConditions[0]['bool']['must_not']['exists']['field']);
        self::assertArrayHasKey($field, $shouldConditions[1]['terms']);
    }
}
