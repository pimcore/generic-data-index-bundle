<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Model\OpenSearch\Query;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\TermsFilter;

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
