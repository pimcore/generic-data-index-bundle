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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\OpenSearch\Query;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\DateFilter;

/**
 * @internal
 */
final class DateFilterTest extends Unit
{
    public function testToArray(): void
    {
        $dateFilter = new DateFilter('datefield', strtotime('2000-01-01'), strtotime('2099-12-31'));

        self::assertSame([
            'range' => [
                'datefield' => [
                    'gt' => '2000-01-01T00:00:00+00:00',
                    'lt' => '2099-12-31T23:59:59+00:00',
                ],
            ],
        ], $dateFilter->toArray(true));

        self::assertSame([
            'datefield' => [
                'gt' => '2000-01-01T00:00:00+00:00',
                'lt' => '2099-12-31T23:59:59+00:00',
            ],
        ], $dateFilter->toArray());


        $dateFilter = new DateFilter('datefield', strtotime('2000-01-01'));

        self::assertSame([
            'range' => [
                'datefield' => [
                    'gt' => '2000-01-01T00:00:00+00:00',
                ],
            ],
        ], $dateFilter->toArray(true));


        $dateFilter = new DateFilter('datefield', null, strtotime('2000-01-01'));

        self::assertSame([
            'range' => [
                'datefield' => [
                    'lt' => '2000-01-01T23:59:59+00:00',
                ],
            ],
        ], $dateFilter->toArray(true));


        $dateFilter = new DateFilter('datefield', null, null, strtotime('2000-01-01'));

        self::assertSame([
            'range' => [
                'datefield' => [
                    'gte' => '2000-01-01T00:00:00+00:00',
                    'lte' => '2000-01-01T23:59:59+00:00',
                ],
            ],
        ], $dateFilter->toArray(true));
    }

    public function testGetType(): void
    {
        $dateFilter = new DateFilter('datefield', strtotime('2000-01-01'), strtotime('2099-12-31'));

        self::assertSame('range', $dateFilter->getType());
    }

    public function testGetParams(): void
    {
        $dateFilter = new DateFilter('datefield', strtotime('2000-01-01'), strtotime('2099-12-31'));

        self::assertSame([
            'datefield' => [
                'gt' => '2000-01-01T00:00:00+00:00',
                'lt' => '2099-12-31T23:59:59+00:00',
            ],
        ], $dateFilter->getParams());

        $dateFilter = new DateFilter('datefield', strtotime('2000-01-01'));

        self::assertSame([
            'datefield' => [
                'gt' => '2000-01-01T00:00:00+00:00',
            ],
        ], $dateFilter->getParams());


        $dateFilter = new DateFilter('datefield', null, strtotime('2000-01-01'));

        self::assertSame([
            'datefield' => [
                'lt' => '2000-01-01T23:59:59+00:00',
            ],
        ], $dateFilter->getParams());


        $dateFilter = new DateFilter('datefield', null, null, strtotime('2000-01-01'));

        self::assertSame([
            'datefield' => [
                'gte' => '2000-01-01T00:00:00+00:00',
                'lte' => '2000-01-01T23:59:59+00:00',
            ],
        ], $dateFilter->getParams());

        $dateFilter = new DateFilter('datefield', null, null, strtotime('2000-01-01'));

        self::assertSame([
            'datefield' => [
                'gte' => '2000-01-01T00:00:00+00:00',
                'lte' => '2000-01-01T23:59:59+00:00',
            ],
        ], $dateFilter->getParams());
    }
    
    public function testCreateFromArray(): void
    {
        $dateFilter = DateFilter::createFromArray('datefield', [
            DateFilter::PARAM_END => strtotime('2000-01-01'),
            DateFilter::PARAM_START => strtotime('2099-12-31'),
            DateFilter::PARAM_ON => strtotime('2010-06-06'),
        ], false);

        self::assertSame($dateFilter->getField(), 'datefield');
        self::assertSame($dateFilter->getEndTimestamp(), strtotime('2000-01-01'));
        self::assertSame($dateFilter->getStartTimestamp(), strtotime('2099-12-31'));
        self::assertSame($dateFilter->getOnTimestamp(), strtotime('2010-06-06'));
        self::assertFalse($dateFilter->isRoundToDay());
    }

    public function testRoundToDay(): void
    {
        $dateFilter = new DateFilter('datefield', strtotime('2000-01-01 12:00:00'), strtotime('2099-12-31 20:00:00'), null, false);

        self::assertSame([
            'datefield' => [
                'gt' => '2000-01-01T12:00:00+00:00',
                'lt' => '2099-12-31T20:00:00+00:00',
            ],
        ], $dateFilter->getParams());

        $dateFilter = new DateFilter('datefield', strtotime('2000-01-01 12:00:00'), strtotime('2099-12-31 20:00:00'), null, true);

        self::assertSame([
            'datefield' => [
                'gt' => '2000-01-01T00:00:00+00:00',
                'lt' => '2099-12-31T23:59:59+00:00',
            ],
        ], $dateFilter->getParams());

        $dateFilter = new DateFilter('datefield', null, null, strtotime('2000-01-01 12:00:00'),false);

        self::assertSame([
            'datefield' => [
                'gte' => '2000-01-01T12:00:00+00:00',
                'lte' => '2000-01-01T12:00:00+00:00',
            ],
        ], $dateFilter->getParams());

        $dateFilter = new DateFilter('datefield', null, null, strtotime('2000-01-01 12:00:00'), true);

        self::assertSame([
            'datefield' => [
                'gte' => '2000-01-01T00:00:00+00:00',
                'lte' => '2000-01-01T23:59:59+00:00',
            ],
        ], $dateFilter->getParams());

    }

    public function testInvalidParameterInCreateFromArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateFilter::createFromArray('datefield', [
            'invalid' => 'invalid',
        ]);
    }
}
