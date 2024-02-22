<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     PCL
 */


namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Modifier\Sort\Tree;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\Search\SortDirection;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Sort\Tree\OrderByFullPath;
use Codeception\Test\Unit;

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