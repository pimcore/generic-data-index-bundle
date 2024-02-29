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
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\TermFilter;

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
