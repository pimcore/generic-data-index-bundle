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
