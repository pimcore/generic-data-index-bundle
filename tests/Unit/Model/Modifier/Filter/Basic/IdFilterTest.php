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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IdFilter;
use Pimcore\Model\User;

/**
 * @internal
 */
final class IdFilterTest extends Unit
{
    public function testIdFilterWithNegativeInteger(): void
    {
        $this->expectException(InvalidModifierException::class);
        $this->expectExceptionMessage("ID must be a positive integer.");
        new IdFilter(-10);
    }

    public function testIdFilterWithZero(): void
    {
        $this->expectException(InvalidModifierException::class);
        $this->expectExceptionMessage("ID must be a positive integer.");
        new IdFilter(0);
    }

    public function testGetId(): void
    {
        $filter = new IdFilter(10);
        $this->assertSame(10, $filter->getId());
    }
}