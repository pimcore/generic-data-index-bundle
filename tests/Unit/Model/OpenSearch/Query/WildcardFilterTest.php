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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\OpenSearch\Query;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\WildcardFilterMode;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\WildcardFilter;

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
                        ]
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
                    ]
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
            ]
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
            ]
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
            ]
        ], $termFilter->toArrayAsSubQuery());

        $termFilter = new WildcardFilter('field', 'value', WildcardFilterMode::PREFIX, false);

        self::assertSame([
            'wildcard' => [
                'field' => [
                    'value' => '*value',
                    'case_insensitive' => false,
                ],
            ]
        ], $termFilter->toArrayAsSubQuery());

        $termFilter = new WildcardFilter('field', 'value', WildcardFilterMode::SUFFIX, false);

        self::assertSame([
            'wildcard' => [
                'field' => [
                    'value' => 'value*',
                    'case_insensitive' => false,
                ],
            ]
        ], $termFilter->toArrayAsSubQuery());

        $termFilter = new WildcardFilter('field', 'value', WildcardFilterMode::SUFFIX, false);

        self::assertSame([
            'wildcard' => [
                'field' => [
                    'value' => 'value*',
                    'case_insensitive' => false,
                ],
            ]
        ], $termFilter->toArrayAsSubQuery());



        $termFilter = new WildcardFilter('field', 'value', WildcardFilterMode::NONE, false);

        self::assertSame([
            'wildcard' => [
                'field' => [
                    'value' => 'value',
                    'case_insensitive' => false,
                ],
            ]
        ], $termFilter->toArrayAsSubQuery());
    }
}
