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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\Filter\FieldType\MultiSelectFilter;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Modifier\Filter\FieldTypeFilters;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchPqlFieldNameTransformationServiceInterface;

/**
 * @internal
 */
final class FieldTypeFiltersTest extends Unit
{
    /**
     * @throws Exception
     */
    public function testMultiSelectQueryMatchesTermsOnly(): void
    {
        $query = $this->getFieldTypeFilters()->getMultiSelectQuery(new MultiSelectFilter('color', ['red', 'blue']));

        $this->assertSame([
            'terms' => ['color' => ['red', 'blue']],
        ], $query->toArrayAsSubQuery());
    }

    /**
     * An empty string stands for "no value": it matches a missing field (null) as well as ''
     * (pimcore/platform-version#394).
     *
     * @throws Exception
     */
    public function testMultiSelectQueryWithEmptyValueAlsoMatchesMissingField(): void
    {
        $query = $this->getFieldTypeFilters()->getMultiSelectQuery(new MultiSelectFilter('color', ['', 'red']));

        $this->assertSame([
            'bool' => [
                'should' => [
                    ['bool' => ['must_not' => ['exists' => ['field' => 'color']]]],
                    ['terms' => ['color' => ['', 'red']]],
                ],
                'minimum_should_match' => 1,
            ],
        ], $query->toArrayAsSubQuery());
    }

    /**
     * @throws Exception
     */
    public function testMultiSelectQueryWithEmptyValueUsesPrefixedField(): void
    {
        $query = $this->getFieldTypeFilters()->getMultiSelectQuery(new MultiSelectFilter('color', ['']), 'brick');

        $this->assertSame([
            'bool' => [
                'should' => [
                    ['bool' => ['must_not' => ['exists' => ['field' => 'brick.color']]]],
                    ['terms' => ['brick.color' => ['']]],
                ],
                'minimum_should_match' => 1,
            ],
        ], $query->toArrayAsSubQuery());
    }

    /**
     * @throws Exception
     */
    private function getFieldTypeFilters(): FieldTypeFilters
    {
        return new FieldTypeFilters($this->makeEmpty(SearchPqlFieldNameTransformationServiceInterface::class));
    }
}
