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
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermsFilter;

/**
 * @internal
 */
final class TermsFilterTest extends Unit
{
    public function testToArray(): void
    {
        $termFilter = new TermsFilter('field', ['value', 'value2']);

        self::assertSame([
            'bool' => [
                'filter' =>
                    ['terms' => ['field' => ['value', 'value2']]],
            ],
        ], $termFilter->toArray(true));

        self::assertSame([
            'filter' =>
                ['terms' => ['field' => ['value', 'value2']]],
        ], $termFilter->toArray());
    }

    public function testToArrayAsSubQuery(): void
    {
        $termFilter = new TermsFilter('field', ['value', 'value2']);

        self::assertSame([
            'terms' => ['field' => ['value', 'value2']],
        ], $termFilter->toArrayAsSubQuery());
    }
}
