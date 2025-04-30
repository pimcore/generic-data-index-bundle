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
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Query\Query;

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
