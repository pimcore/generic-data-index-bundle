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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\DefaultSearch\Query;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TimeFilter;

/**
 * @internal
 */
final class TimeFilterTest extends Unit
{
    public function testToArray(): void
    {
        $timeFilter = new TimeFilter('datefield', '12:15', '13:15');

        self::assertSame([
            'range' => [
                'datefield' => [
                    'gte' => '12:15',
                    'lte' => '13:15',
                ],
            ],
        ], $timeFilter->toArray(true));

        $timeFilter = new TimeFilter('datefield', '12:45');

        self::assertSame([
            'range' => [
                'datefield' => [
                    'gte' => '12:45',
                ],
            ],
        ], $timeFilter->toArray(true));

        $timeFilter = new TimeFilter('datefield', null, '13:00');

        self::assertSame([
            'range' => [
                'datefield' => [
                    'lte' => '13:00',
                ],
            ],
        ], $timeFilter->toArray(true));

        $timeFilter = new TimeFilter('datefield', null, null, '12:30');

        self::assertSame([
            'range' => [
                'datefield' => [
                    'gte' => '12:30',
                    'lte' => '12:30',
                ],
            ],
        ], $timeFilter->toArray(true));
    }

    public function testGetType(): void
    {
        $dateFilter = new TimeFilter('datefield', '12:30', '12:45');

        self::assertSame('range', $dateFilter->getType());
    }

    public function testGetParams(): void
    {
        $timeFilter = new TimeFilter('datefield', '12:15', '13:15');

        self::assertSame([
            'datefield' => [
                'gte' => '12:15',
                'lte' => '13:15',
            ],
        ], $timeFilter->getParams());

        $timeFilter = new TimeFilter('datefield', '12:45');

        self::assertSame([
            'datefield' => [
                'gte' => '12:45',
            ],
        ], $timeFilter->getParams());

        $timeFilter = new TimeFilter('datefield', null, '13:00');

        self::assertSame([
            'datefield' => [
                'lte' => '13:00',
            ],
        ], $timeFilter->getParams());

        $timeFilter = new TimeFilter('datefield', null, null, '12:30');

        self::assertSame([
            'datefield' => [
                'gte' => '12:30',
                'lte' => '12:30',
            ],
        ], $timeFilter->getParams());
    }
}
