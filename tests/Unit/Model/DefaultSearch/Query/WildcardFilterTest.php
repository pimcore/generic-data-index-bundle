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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DefaultSearch\WildcardFilterMode;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\WildcardFilter;

/**
 * @internal
 */
final class WildcardFilterTest extends Unit
{
    public function testToArray(): void
    {
        $termFilter = new WildcardFilter('field', 'value');

        self::assertSame([
            'bool' => [
                'filter' =>
                    [
                        'wildcard' => [
                            'field' => [
                                'value' => '*value*',
                                'case_insensitive' => true,
                            ],
                        ],
                    ],
                ],
            ], $termFilter->toArray(true));

        self::assertSame([
            'filter' =>
                [
                    'wildcard' => [
                        'field' => [
                            'value' => '*value*',
                            'case_insensitive' => true,
                        ],
                    ],
                ],
            ], $termFilter->toArray());
    }

    public function testToArrayAsSubQuery(): void
    {
        $termFilter = new WildcardFilter('field', 'value');

        self::assertSame([
            'wildcard' => [
                'field' => [
                    'value' => '*value*',
                    'case_insensitive' => true,
                ],
            ],
        ], $termFilter->toArrayAsSubQuery());
    }

    public function testCaseInsensitive(): void
    {
        $termFilter = new WildcardFilter('field', 'value', WildcardFilterMode::BOTH, false);

        self::assertSame([
            'wildcard' => [
                'field' => [
                    'value' => '*value*',
                    'case_insensitive' => false,
                ],
            ],
        ], $termFilter->toArrayAsSubQuery());
    }

    public function testDefaultWildcardModes(): void
    {
        $termFilter = new WildcardFilter('field', 'value', WildcardFilterMode::BOTH, false);

        self::assertSame([
            'wildcard' => [
                'field' => [
                    'value' => '*value*',
                    'case_insensitive' => false,
                ],
            ],
        ], $termFilter->toArrayAsSubQuery());

        $termFilter = new WildcardFilter('field', 'value', WildcardFilterMode::PREFIX, false);

        self::assertSame([
            'wildcard' => [
                'field' => [
                    'value' => '*value',
                    'case_insensitive' => false,
                ],
            ],
        ], $termFilter->toArrayAsSubQuery());

        $termFilter = new WildcardFilter('field', 'value', WildcardFilterMode::SUFFIX, false);

        self::assertSame([
            'wildcard' => [
                'field' => [
                    'value' => 'value*',
                    'case_insensitive' => false,
                ],
            ],
        ], $termFilter->toArrayAsSubQuery());

        $termFilter = new WildcardFilter('field', 'value', WildcardFilterMode::SUFFIX, false);

        self::assertSame([
            'wildcard' => [
                'field' => [
                    'value' => 'value*',
                    'case_insensitive' => false,
                ],
            ],
        ], $termFilter->toArrayAsSubQuery());

        $termFilter = new WildcardFilter('field', 'value', WildcardFilterMode::NONE, false);

        self::assertSame([
            'wildcard' => [
                'field' => [
                    'value' => 'value',
                    'case_insensitive' => false,
                ],
            ],
        ], $termFilter->toArrayAsSubQuery());
    }
}
