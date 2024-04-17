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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Modifier\Filter\Tree;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\TagFilter;
use ValueError;

/**
 * @internal
 */
final class TagFilterTest extends Unit
{
    public function testTagFilterWithNegativeInteger(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Provided integer must be positive. (-2 given)');
        new TagFilter([5, -2]);
    }

    public function testTagFilterWithZero(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Provided integer must be positive. (0 given)');
        new TagFilter([0]);
    }

    public function testGetTagFilterParameters(): void
    {
        $tagIds = [2, 5, 15];
        $filter = new TagFilter($tagIds);

        $this->assertCount(3, $filter->getTagIds());
        $this->assertSame($tagIds, $filter->getTagIds());
        $this->assertFalse($filter->isIncludeChildTags());

        $filter = new TagFilter($tagIds, true);
        $this->assertTrue($filter->isIncludeChildTags());
    }
}
