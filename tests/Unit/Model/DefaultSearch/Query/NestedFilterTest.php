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
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\NestedFilter;

/**
 * @internal
 */
final class NestedFilterTest extends Unit
{
    public function testToArray(): void
    {
        $subQuery = [
            'bool' => [
                'must' => [
                    ['term' => ['nested_field' => 'value']],
                ],
            ],
        ];

        $nestedFilter = new NestedFilter('nested_path', $subQuery);

        self::assertSame([
            'bool' => [
                'filter' => [
                    'nested' => [
                        'path' => 'nested_path',
                        'query' => $subQuery,
                    ],
                ],
            ],
        ], $nestedFilter->toArray(true));

        self::assertSame([
            'filter' => [
                'nested' => [
                    'path' => 'nested_path',
                    'query' => $subQuery,
                ],
            ],
        ], $nestedFilter->toArray());
    }

    public function testToArrayAsSubQuery(): void
    {
        $subQuery = [
            'bool' => [
                'must' => [
                    ['term' => ['nested_field' => 'value']],
                ],
            ],
        ];

        $nestedFilter = new NestedFilter('nested_path', $subQuery);

        self::assertSame([
            'nested' => [
                'path' => 'nested_path',
                'query' => $subQuery,
            ],
        ], $nestedFilter->toArrayAsSubQuery());
    }

    public function testGetPath(): void
    {
        $subQuery = [
            'term' => ['nested_field' => 'value'],
        ];

        $nestedFilter = new NestedFilter('my.nested.path', $subQuery);

        self::assertSame('my.nested.path', $nestedFilter->getPath());
    }

    public function testWithSimpleSubQuery(): void
    {
        $subQuery = [
            'term' => ['status' => 'active'],
        ];

        $nestedFilter = new NestedFilter('items', $subQuery);

        self::assertSame([
            'bool' => [
                'filter' => [
                    'nested' => [
                        'path' => 'items',
                        'query' => $subQuery,
                    ],
                ],
            ],
        ], $nestedFilter->toArray(true));

        self::assertSame([
            'nested' => [
                'path' => 'items',
                'query' => $subQuery,
            ],
        ], $nestedFilter->toArrayAsSubQuery());
    }

    public function testWithComplexSubQuery(): void
    {
        $subQuery = [
            'bool' => [
                'must' => [
                    ['term' => ['items.category' => 'electronics']],
                    ['range' => ['items.price' => ['gte' => 100, 'lte' => 500]]],
                ],
                'must_not' => [
                    ['term' => ['items.discontinued' => true]],
                ],
            ],
        ];

        $nestedFilter = new NestedFilter('items', $subQuery);

        self::assertSame([
            'bool' => [
                'filter' => [
                    'nested' => [
                        'path' => 'items',
                        'query' => $subQuery,
                    ],
                ],
            ],
        ], $nestedFilter->toArray(true));

        self::assertSame([
            'nested' => [
                'path' => 'items',
                'query' => $subQuery,
            ],
        ], $nestedFilter->toArrayAsSubQuery());

        self::assertSame('items', $nestedFilter->getPath());
    }

    public function testIsEmpty(): void
    {
        $emptySubQuery = [];
        $nestedFilter = new NestedFilter('path', $emptySubQuery);

        // Since NestedFilter extends BoolQuery, it should inherit isEmpty() method
        // With empty subQuery, the filter should still not be considered empty
        // because it has a valid nested structure
        self::assertFalse($nestedFilter->isEmpty());

        $validSubQuery = ['term' => ['field' => 'value']];
        $nestedFilterWithQuery = new NestedFilter('path', $validSubQuery);
        self::assertFalse($nestedFilterWithQuery->isEmpty());
    }
}
