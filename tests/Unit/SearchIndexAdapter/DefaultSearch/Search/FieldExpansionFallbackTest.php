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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DefaultSearch\Search;

use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\FieldExpansionFallback;

/**
 * @internal
 */
final class FieldExpansionFallbackTest extends Unit
{
    private const ERROR = 'failed to create query: field expansion matches too many fields, limit: 4096, got: 82499';

    public function testFieldlessSimpleQueryStringIsRestrictedInNestedQueries(): void
    {
        $body = [
            'query' => [
                'bool' => [
                    'filter' => [
                        ['simple_query_string' => ['query' => 'pro', 'default_operator' => 'AND']],
                        ['bool' => ['should' => [['simple_query_string' => ['query' => 'x', 'fields' => []]]]]],
                    ],
                ],
            ],
        ];

        $restricted = FieldExpansionFallback::restrictFullTextFields($body);

        $this->assertSame(
            FieldExpansionFallback::FIELDS,
            $restricted['query']['bool']['filter'][0]['simple_query_string']['fields']
        );
        $this->assertSame(
            FieldExpansionFallback::FIELDS,
            $restricted['query']['bool']['filter'][1]['bool']['should'][0]['simple_query_string']['fields']
        );
        $this->assertSame('pro', $restricted['query']['bool']['filter'][0]['simple_query_string']['query']);
    }

    public function testExplicitFieldsAreKept(): void
    {
        $body = ['query' => ['simple_query_string' => ['query' => 'pro', 'fields' => ['custom.field']]]];

        $this->assertNull(FieldExpansionFallback::restrictFullTextFields($body));
    }

    public function testNothingToRestrictWithoutSimpleQueryString(): void
    {
        $this->assertNull(FieldExpansionFallback::restrictFullTextFields(['query' => ['match_all' => []]]));
        $this->assertNull(FieldExpansionFallback::restrictFullTextFields([]));
    }

    public function testFieldExpansionErrorIsDetectedInPreviousExceptions(): void
    {
        $this->assertTrue(FieldExpansionFallback::isFieldExpansionError(new Exception(self::ERROR)));
        $this->assertTrue(FieldExpansionFallback::isFieldExpansionError(
            new Exception('Failed to count data', 0, new Exception(self::ERROR))
        ));
        $this->assertFalse(FieldExpansionFallback::isFieldExpansionError(new Exception('Result window is too large')));
    }

    public function testRequestIsRepeatedOnceWithRestrictedFields(): void
    {
        $bodies = [];
        $request = function (array $params) use (&$bodies) {
            $bodies[] = $params['body'];
            if (count($bodies) === 1) {
                throw new Exception(self::ERROR);
            }

            return ['count' => 1];
        };

        $result = FieldExpansionFallback::execute(
            $request,
            ['index' => 'i', 'body' => ['query' => ['simple_query_string' => ['query' => 'pro']]]]
        );

        $this->assertSame(['count' => 1], $result);
        $this->assertCount(2, $bodies);
        $this->assertArrayNotHasKey('fields', $bodies[0]['query']['simple_query_string']);
        $this->assertSame(FieldExpansionFallback::FIELDS, $bodies[1]['query']['simple_query_string']['fields']);
    }

    public function testRequestIsRepeatedWhenAShardFailedWithFieldExpansionError(): void
    {
        $bodies = [];
        $request = function (array $params) use (&$bodies) {
            $bodies[] = $params['body'];
            if (count($bodies) === 1) {
                return [
                    '_shards' => [
                        'total' => 2,
                        'successful' => 1,
                        'failed' => 1,
                        'failures' => [['index' => 'wide', 'reason' => ['reason' => self::ERROR]]],
                    ],
                    'hits' => ['hits' => []],
                ];
            }

            return ['_shards' => ['total' => 2, 'successful' => 2, 'failed' => 0], 'hits' => ['hits' => [1]]];
        };

        $result = FieldExpansionFallback::execute(
            $request,
            ['body' => ['query' => ['simple_query_string' => ['query' => 'pro']]]]
        );

        $this->assertSame([1], $result['hits']['hits']);
        $this->assertCount(2, $bodies);
        $this->assertSame(FieldExpansionFallback::FIELDS, $bodies[1]['query']['simple_query_string']['fields']);
    }

    public function testResponsesWithOtherShardFailuresAreReturnedAsIs(): void
    {
        $calls = 0;
        $response = ['_shards' => ['failed' => 1, 'failures' => [['reason' => ['reason' => 'other problem']]]]];
        $request = function () use (&$calls, $response) {
            $calls++;

            return $response;
        };

        $result = FieldExpansionFallback::execute(
            $request,
            ['body' => ['query' => ['simple_query_string' => ['query' => 'pro']]]]
        );

        $this->assertSame($response, $result);
        $this->assertSame(1, $calls);
    }

    public function testShardFailureWithoutAnythingToRestrictReturnsTheResponse(): void
    {
        $calls = 0;
        $response = ['_shards' => ['failed' => 1, 'failures' => [['reason' => ['reason' => self::ERROR]]]]];
        $request = function () use (&$calls, $response) {
            $calls++;

            return $response;
        };

        $result = FieldExpansionFallback::execute($request, ['body' => ['query' => ['match_all' => []]]]);

        $this->assertSame($response, $result);
        $this->assertSame(1, $calls);
    }

    public function testOtherErrorsAreNotRetried(): void
    {
        $calls = 0;
        $request = function () use (&$calls) {
            $calls++;

            throw new Exception('search failed');
        };

        $this->expectExceptionMessage('search failed');

        try {
            FieldExpansionFallback::execute(
                $request,
                ['body' => ['query' => ['simple_query_string' => ['query' => 'pro']]]]
            );
        } finally {
            $this->assertSame(1, $calls);
        }
    }

    public function testExpansionErrorIsNotRetriedWithoutAnythingToRestrict(): void
    {
        $calls = 0;
        $request = function () use (&$calls) {
            $calls++;

            throw new Exception(self::ERROR);
        };

        $this->expectExceptionMessage('field expansion matches too many fields');

        try {
            FieldExpansionFallback::execute($request, ['body' => ['query' => ['match_all' => []]]]);
        } finally {
            $this->assertSame(1, $calls);
        }
    }

    public function testFailureOfTheRepeatedRequestIsThrown(): void
    {
        $calls = 0;
        $request = function () use (&$calls) {
            $calls++;

            throw new Exception(self::ERROR . ' (attempt ' . $calls . ')');
        };

        $this->expectExceptionMessage('attempt 2');

        try {
            FieldExpansionFallback::execute(
                $request,
                ['body' => ['query' => ['simple_query_string' => ['query' => 'pro']]]]
            );
        } finally {
            $this->assertSame(2, $calls);
        }
    }
}
