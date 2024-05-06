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
