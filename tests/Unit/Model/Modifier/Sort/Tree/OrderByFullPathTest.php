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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Modifier\Sort\Tree;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Search\SortDirection;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;

/**
 * @internal
 */
final class OrderByFullPathTest extends Unit
{
    public function testGetAscDirection(): void
    {
        $filter = new OrderByFullPath(SortDirection::ASC);
        $this->assertSame(SortDirection::ASC, $filter->getDirection());
    }

    public function testGetDescDirection(): void
    {
        $filter = new OrderByFullPath(SortDirection::DESC);
        $this->assertSame(SortDirection::DESC, $filter->getDirection());
    }
}
