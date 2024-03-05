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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Modifier\Filter\Basic;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdsFilter;
use ValueError;

/**
 * @internal
 */
final class IdsFilterTest extends Unit
{
    public function testIdsFilterWithNegativeInteger(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Provided integer must be positive. (-10 given)');
        new IdsFilter([1, 2, -10, 5]);
    }

    public function testIdsFilterWithZero(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Provided integer must be positive. (0 given)');
        new IdsFilter([1, 2, 0, 5]);
    }

    public function testIdsFilterWithString(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Provided array must contain only integer values. (string given)');
        new IdsFilter([1, 2, 'string', 5]);
    }

    public function testGetIds(): void
    {
        $filter = new IdsFilter([1, 2, 10, 5]);

        $this->assertSame([1, 2, 10, 5], $filter->getIds());
    }
}
