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
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\SimpleQueryStringFilter;

/**
 * @internal
 */
final class SimpleQueryStringFilterTest extends Unit
{
    public function testDefaultsUseAndOperatorAndSafeFlags(): void
    {
        $filter = new SimpleQueryStringFilter('Ford 1.1-1');

        self::assertSame([
            'simple_query_string' => [
                'query' => 'Ford 1.1-1',
                'default_operator' => 'AND',
                'flags' => 'PHRASE|WHITESPACE',
            ],
        ], $filter->toArrayAsSubQuery());

        self::assertSame([
            'bool' => [
                'filter' => [
                    'simple_query_string' => [
                        'query' => 'Ford 1.1-1',
                        'default_operator' => 'AND',
                        'flags' => 'PHRASE|WHITESPACE',
                    ],
                ],
            ],
        ], $filter->toArray(true));
    }

    public function testFieldsAreOmittedWhenEmpty(): void
    {
        $filter = new SimpleQueryStringFilter('value');

        self::assertArrayNotHasKey('fields', $filter->toArrayAsSubQuery()['simple_query_string']);
    }

    public function testFieldsAndFlagsAreConfigurable(): void
    {
        $filter = new SimpleQueryStringFilter(
            'value',
            'OR',
            ['key^3', 'fullPath'],
            'ALL',
        );

        self::assertSame([
            'simple_query_string' => [
                'query' => 'value',
                'default_operator' => 'OR',
                'fields' => ['key^3', 'fullPath'],
                'flags' => 'ALL',
            ],
        ], $filter->toArrayAsSubQuery());
    }

    public function testFlagsAreOmittedWhenNull(): void
    {
        $filter = new SimpleQueryStringFilter('value', 'AND', [], null);

        self::assertArrayNotHasKey('flags', $filter->toArrayAsSubQuery()['simple_query_string']);
    }

    public function testGetTerm(): void
    {
        $filter = new SimpleQueryStringFilter('value');

        self::assertSame('value', $filter->getTerm());
    }
}
