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
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\Query;

/**
 * @internal
 */
final class QueryTest extends Unit
{
    public function testIsEmpty(): void
    {
        $query = new Query('multi_match', []);

        self::assertTrue($query->isEmpty());

        $query = new Query('multi_match', [
            'query' => 'search',
            'fields' => ['field1', 'field2'],
        ]);
        self::assertFalse($query->isEmpty());
    }

    public function testToArray(): void
    {
        $query = new Query('multi_match', [
            'query' => 'search',
            'fields' => ['field1', 'field2'],
        ]);

        self::assertSame([
            'multi_match' => [
                'query' => 'search',
                'fields' => ['field1', 'field2'],
            ],
        ], $query->toArray(true));

    }
}
