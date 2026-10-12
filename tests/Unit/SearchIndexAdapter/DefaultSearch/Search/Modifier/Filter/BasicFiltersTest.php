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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DefaultSearch\Search\Modifier\Filter;

use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\IntegerFilter;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\Basic\NumberFilter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\Filter\BasicFilters;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchPqlFieldNameTransformationServiceInterface;

/**
 * @internal
 */
final class BasicFiltersTest extends Unit
{
    /**
     * Regression test: a NumberFilter with a decimal value must not fail with a TypeError
     * (pimcore/platform-version#589).
     *
     * @throws Exception
     */
    public function testNumberQueryWithDecimalSearchTerm(): void
    {
        $termFilter = $this->getBasicFilters()->getNumberQuery(new NumberFilter('lotSize', 2.1));

        $this->assertSame([
            'term' => ['lotSize' => '2.1'],
        ], $termFilter->toArrayAsSubQuery());
    }

    /**
     * @throws Exception
     */
    public function testNumberQueryKeepsFullFloatPrecision(): void
    {
        $termFilter = $this->getBasicFilters()->getNumberQuery(new NumberFilter('value', 0.1 + 0.2));

        $this->assertSame('0.30000000000000004', $termFilter->getTerm());
    }

    /**
     * @throws Exception
     */
    public function testNumberQueryKeepsIntegerSearchTerm(): void
    {
        $basicFilters = $this->getBasicFilters();

        $this->assertSame(2, $basicFilters->getNumberQuery(new NumberFilter('lotSize', 2))->getTerm());
        $this->assertSame(5, $basicFilters->getNumberQuery(new IntegerFilter('lotSize', 5))->getTerm());
    }

    /**
     * @throws Exception
     */
    private function getBasicFilters(): BasicFilters
    {
        return new BasicFilters($this->makeEmpty(SearchPqlFieldNameTransformationServiceInterface::class));
    }
}
