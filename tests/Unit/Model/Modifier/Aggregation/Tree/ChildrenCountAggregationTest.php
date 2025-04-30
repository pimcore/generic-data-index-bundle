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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Modifier\Aggregation\Tree;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Tree\ChildrenCountAggregation;
use ValueError;

/**
 * @internal
 */
final class ChildrenCountAggregationTest extends Unit
{
    public function testChildrenCountAggregationWithNegativeInteger(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Provided integer must be positive. (-10 given)');
        new ChildrenCountAggregation([1, 2, -10, 5]);
    }

    public function testChildrenCountAggregationWithZero(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Provided integer must be positive. (0 given)');
        new ChildrenCountAggregation([1, 2, 0, 5]);
    }

    public function testChildrenCountAggregationWithString(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Provided array must contain only integer values. (string given)');
        new ChildrenCountAggregation([1, 2, 'string', 5]);
    }

    public function testGetParentIds(): void
    {
        $filter = new ChildrenCountAggregation([1, 2, 10, 5]);

        $this->assertSame([1, 2, 10, 5], $filter->getParentIds());
    }

    public function testGetAggregationName(): void
    {
        $filter = new ChildrenCountAggregation([1, 2, 10, 5], 'name');

        $this->assertSame('name', $filter->getAggregationName());
    }
}
