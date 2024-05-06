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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\ParentIdFilter;
use ValueError;

/**
 * @internal
 */
final class ParentIdFilterTest extends Unit
{
    public function testParentIdFilterWithNegativeInteger(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Provided integer must be positive. (-10 given)');
        new ParentIdFilter(-10);
    }

    public function testParentIdFilterWithZero(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Provided integer must be positive. (0 given)');
        new ParentIdFilter(0);
    }

    public function testGetParentId(): void
    {
        $filter = new ParentIdFilter(10);
        $this->assertSame(10, $filter->getParentId());
    }
}
