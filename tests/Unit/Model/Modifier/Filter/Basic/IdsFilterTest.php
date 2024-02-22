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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Modifier\Filter\Basic;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidModifierException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdsFilter;
/**
 * @internal
 */
final class IdsFilterTest extends Unit
{
    public function testIdsFilterWithNegativeInteger(): void
    {
        $this->expectException(InvalidModifierException::class);
        $this->expectExceptionMessage("Value must be a positive integer.");
        new IdsFilter([1,2,-10,5]);
    }

    public function testIdsFilterWithZero(): void
    {
        $this->expectException(InvalidModifierException::class);
        $this->expectExceptionMessage("Value must be a positive integer.");
        new IdsFilter([1,2,0,5]);
    }

    public function testIdsFilterWithString(): void
    {
        $this->expectException(InvalidModifierException::class);
        $this->expectExceptionMessage("Array must contain only integers.");
        new IdsFilter([1,2,'string',5]);
    }

    public function testGetIds(): void
    {
        $filter = new IdsFilter([1,2,10,5]);

        $this->assertSame([1,2,10,5], $filter->getIds());
    }

}