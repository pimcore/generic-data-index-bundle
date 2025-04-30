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
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\TermFilter;

/**
 * @internal
 */
final class TermFilterTest extends Unit
{
    public function testToArray(): void
    {
        $termFilter = new TermFilter('field', 'value');

        self::assertSame([
            'bool' => [
                'filter' =>
                    ['term' => ['field' => 'value']],
            ],
        ], $termFilter->toArray(true));

        self::assertSame([
            'filter' =>
                ['term' => ['field' => 'value']],
        ], $termFilter->toArray());
    }

    public function testToArrayAsSubQuery(): void
    {
        $termFilter = new TermFilter('field', 'value');

        self::assertSame([
            'term' => ['field' => 'value'],
        ], $termFilter->toArrayAsSubQuery());
    }
}
