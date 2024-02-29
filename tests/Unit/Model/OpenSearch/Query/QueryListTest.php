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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\OpenSearch\Query;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\QueryList;

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
}
