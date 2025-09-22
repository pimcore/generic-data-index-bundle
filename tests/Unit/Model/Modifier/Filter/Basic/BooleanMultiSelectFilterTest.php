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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\Modifier\Filter\Basic;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\BooleanMultiSelectFilter;
use ValueError;

/**
 * @internal
 */
final class BooleanMultiSelectFilterTest extends Unit
{
    public function testBooleanMultiSelectFilterWithString(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Provided array must contain only boolean or null values. (string given)');
        new BooleanMultiSelectFilter('field', [true, false, 'string']);
    }

    public function testBooleanMultiSelectFilterWithInteger(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Provided array must contain only boolean or null values. (integer given)');
        new BooleanMultiSelectFilter('field', [true, false, 1]);
    }

    public function testGetField(): void
    {
        $filter = new BooleanMultiSelectFilter('test_field', [true, false]);
        $this->assertSame('test_field', $filter->getField());
    }

    public function testGetValues(): void
    {
        $values = [true, false, null];
        $filter = new BooleanMultiSelectFilter('field', $values);
        $this->assertSame($values, $filter->getValues());
    }

    public function testIsPqlFieldNameResolutionEnabledDefault(): void
    {
        $filter = new BooleanMultiSelectFilter('field', [true, false]);
        $this->assertTrue($filter->isPqlFieldNameResolutionEnabled());
    }

    public function testIsPqlFieldNameResolutionEnabledTrue(): void
    {
        $filter = new BooleanMultiSelectFilter('field', [true, false], true);
        $this->assertTrue($filter->isPqlFieldNameResolutionEnabled());
    }

    public function testIsPqlFieldNameResolutionEnabledFalse(): void
    {
        $filter = new BooleanMultiSelectFilter('field', [true, false], false);
        $this->assertFalse($filter->isPqlFieldNameResolutionEnabled());
    }

    public function testValidBooleanValues(): void
    {
        $filter = new BooleanMultiSelectFilter('field', [true, false]);
        $this->assertSame([true, false], $filter->getValues());
    }

    public function testValidBooleanAndNullValues(): void
    {
        $filter = new BooleanMultiSelectFilter('field', [true, false, null]);
        $this->assertSame([true, false, null], $filter->getValues());
    }

    public function testValidSingleBooleanValue(): void
    {
        $filter = new BooleanMultiSelectFilter('field', [true]);
        $this->assertSame([true], $filter->getValues());
    }

    public function testValidSingleNullValue(): void
    {
        $filter = new BooleanMultiSelectFilter('field', [null]);
        $this->assertSame([null], $filter->getValues());
    }

    public function testComplexFieldName(): void
    {
        $fieldName = 'category.subcategory.active';
        $filter = new BooleanMultiSelectFilter($fieldName, [true, false]);
        $this->assertSame($fieldName, $filter->getField());
    }
}
