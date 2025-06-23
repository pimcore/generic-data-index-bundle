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
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\Query;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\QueryList;

/**
 * @internal
 */
final class QueryListTest extends Unit
{
    public function testIsEmpty(): void
    {
        $queryList = new QueryList();

        self::assertTrue($queryList->isEmpty());

        $queryList->addQuery(new BoolQuery([
            'must' => [
                ['term' => ['field' => 'value']],
            ],
        ]));
        self::assertFalse($queryList->isEmpty());
    }

    public function testAddQuery(): void
    {
        $queryList = new QueryList();

        $queryList->addQuery(new BoolQuery([
            'must' => [
                ['term' => ['field' => 'value']],
            ],
        ]));
        self::assertSame([
            'bool' => [
                'must' =>
                    ['term' => ['field' => 'value']],
            ],
        ], $queryList->toArray());

        $queryList->addQuery(new BoolQuery([
            'must' => [
                ['term' => ['field2' => 'value2']],
            ],
        ]));

        self::assertSame([
            'bool' => [
                'must' => [
                    ['term' => ['field' => 'value']],
                    ['term' => ['field2' => 'value2']],
                ],
            ],
        ], $queryList->toArray());

        $queryList->addQuery(new BoolQuery([
            'should' => [
                ['term' => ['field3' => 'value3']],
            ],
        ]));

        self::assertSame([
            'bool' => [
                'must' => [
                    ['term' => ['field' => 'value']],
                    ['term' => ['field2' => 'value2']],
                ],
                'should' => ['term' => ['field3' => 'value3']],
            ],
        ], $queryList->toArray());
    }

    public function testCombineToBoolQuery(): void
    {
        $queryList = new QueryList();
        $queryList->addQuery(new Query('term', [
            'field1' => 'value1',
        ]));
        $queryList->addQuery(new Query('term', [
            'field2' => 'value2',
        ]));

        self::assertSame([
            'bool' => [
                'filter' => [
                    ['term' => ['field1' => 'value1']],
                    ['term' => ['field2' => 'value2']],
                ],
            ],
        ], $queryList->toArray());

        $queryList = new QueryList();
        $queryList->addQuery(new Query('term', [
            'field1' => 'value1',
        ]));

        $queryList->addQuery(new BoolQuery([
            'should' => [
                ['term' => ['field3' => 'value3']],
            ],
        ]));

        $queryList->addQuery(new Query('term', [
            'field2' => 'value2',
        ]));

        self::assertSame([
            'bool' => [
                'should' => ['term' => ['field3' => 'value3']],
                'filter' => [
                    ['term' => ['field1' => 'value1']],
                    ['term' => ['field2' => 'value2']],
                ],
            ],
        ], $queryList->toArray());
    }
}
