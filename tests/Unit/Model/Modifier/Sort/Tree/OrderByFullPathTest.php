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
