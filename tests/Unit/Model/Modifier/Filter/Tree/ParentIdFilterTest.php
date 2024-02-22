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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidModifierException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Tree\ParentIdFilter;

/**
 * @internal
 */
final class ParentIdFilterTest extends Unit
{
    public function testParentIdFilterWithNegativeInteger(): void
    {
        $this->expectException(InvalidModifierException::class);
        $this->expectExceptionMessage('ID must be a positive integer.');
        new ParentIdFilter(-10);
    }

    public function testParentIdFilterWithZero(): void
    {
        $this->expectException(InvalidModifierException::class);
        $this->expectExceptionMessage('ID must be a positive integer.');
        new ParentIdFilter(0);
    }

    public function testGetParentId(): void
    {
        $filter = new ParentIdFilter(10);
        $this->assertSame(10, $filter->getParentId());
    }
}
