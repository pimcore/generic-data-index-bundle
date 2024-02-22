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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Modifier\Aggregation\Tree;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidModifierException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Aggregation\Tree\ChildrenCountAggregation;

/**
 * @internal
 */
final class ChildrenCountAggregationTest extends Unit
{
    public function testChildrenCountAggregationWithNegativeInteger(): void
    {
        $this->expectException(InvalidModifierException::class);
        $this->expectExceptionMessage('Value must be a positive integer.');
        new ChildrenCountAggregation([1, 2, -10, 5]);
    }

    public function testChildrenCountAggregationWithZero(): void
    {
        $this->expectException(InvalidModifierException::class);
        $this->expectExceptionMessage('Value must be a positive integer.');
        new ChildrenCountAggregation([1, 2, 0, 5]);
    }

    public function testChildrenCountAggregationWithString(): void
    {
        $this->expectException(InvalidModifierException::class);
        $this->expectExceptionMessage('Array must contain only integers.');
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
