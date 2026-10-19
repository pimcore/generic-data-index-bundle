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
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
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
     * Without the opt-in flag an empty string is an ordinary term, so existing callers keep their results.
     *
     * @throws Exception
     */
    public function testMultiSelectQueryWithEmptyValueMatchesTermsOnlyByDefault(): void
    {
        $query = $this->getFieldTypeFilters()->getMultiSelectQuery(new MultiSelectFilter('color', ['']));

        $this->assertSame([
            'terms' => ['color' => ['']],
        ], $query->toArrayAsSubQuery());
    }

    /**
     * With the opt-in flag an empty string stands for "no value": it matches a missing field (null) as well as ''
     * (pimcore/platform-version#394).
     *
     * @throws Exception
     */
    public function testMultiSelectQueryWithEmptyValueAlsoMatchesMissingFieldWhenEnabled(): void
    {
        $query = $this->getFieldTypeFilters()->getMultiSelectQuery(
            new MultiSelectFilter('color', ['', 'red'], matchMissingForEmptyValue: true)
        );

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
    public function testMultiSelectQueryWithoutEmptyValueMatchesTermsOnlyWhenEnabled(): void
    {
        $query = $this->getFieldTypeFilters()->getMultiSelectQuery(
            new MultiSelectFilter('color', ['red'], matchMissingForEmptyValue: true)
        );

        $this->assertSame([
            'terms' => ['color' => ['red']],
        ], $query->toArrayAsSubQuery());
    }

    /**
     * Inside a nested filter (classification store) a missing key cannot be told apart from an absent group,
     * so the option is not applied there and the filter keeps exact matching.
     *
     * @throws Exception
     */
    public function testMultiSelectQueryKeepsExactMatchingInsideNestedFilters(): void
    {
        $query = $this->getFieldTypeFilters()->getMultiSelectQuery(
            new MultiSelectFilter('color', [''], matchMissingForEmptyValue: true),
            'standard_fields.store.group.default'
        );

        $this->assertSame([
            'terms' => ['standard_fields.store.group.default.color' => ['']],
        ], $query->toArrayAsSubQuery());
    }

    /**
     * The keyword subfield skips values longer than ignore_above, so presence is checked on the field itself.
     *
     * @throws Exception
     */
    public function testMultiSelectQueryChecksPresenceOnTheFieldNotItsKeywordSubfield(): void
    {
        $filters = new FieldTypeFilters($this->makeEmpty(SearchPqlFieldNameTransformationServiceInterface::class, [
            'transformFieldnameForSearch' => 'standard_fields.color.keyword',
            'isKeywordMultiField' => static fn (
                SearchInterface $search,
                string $fieldName
            ): bool => $fieldName === 'standard_fields.color.keyword',
        ]));

        $query = $filters->getMultiSelectQuery(
            new MultiSelectFilter('color', [''], matchMissingForEmptyValue: true),
            null,
            $this->makeEmpty(SearchInterface::class)
        );

        $this->assertSame([
            'bool' => [
                'should' => [
                    ['bool' => ['must_not' => ['exists' => ['field' => 'standard_fields.color']]]],
                    ['terms' => ['standard_fields.color.keyword' => ['']]],
                ],
                'minimum_should_match' => 1,
            ],
        ], $query->toArrayAsSubQuery());
    }

    /**
     * Without field name resolution there is no mapping lookup: a field literally named "keyword" is not a
     * keyword subfield, so presence is checked on that field itself.
     *
     * @throws Exception
     */
    public function testMultiSelectQueryChecksPresenceOnALiteralKeywordField(): void
    {
        $filters = new FieldTypeFilters($this->makeEmpty(SearchPqlFieldNameTransformationServiceInterface::class, [
            'isKeywordMultiField' => true,
        ]));

        $query = $filters->getMultiSelectQuery(
            new MultiSelectFilter('standard_fields.keyword', [''], false, true),
            null,
            $this->makeEmpty(SearchInterface::class)
        );

        $this->assertSame([
            'bool' => [
                'should' => [
                    ['bool' => ['must_not' => ['exists' => ['field' => 'standard_fields.keyword']]]],
                    ['terms' => ['standard_fields.keyword' => ['']]],
                ],
                'minimum_should_match' => 1,
            ],
        ], $query->toArrayAsSubQuery());
    }

    /**
     * A resolved field that the mapping does not know as a keyword multi-field (e.g. an object property named
     * "keyword") keeps the presence check on the resolved field.
     *
     * @throws Exception
     */
    public function testMultiSelectQueryKeepsPresenceOnAResolvedFieldThatIsNoKeywordMultiField(): void
    {
        $filters = new FieldTypeFilters($this->makeEmpty(SearchPqlFieldNameTransformationServiceInterface::class, [
            'transformFieldnameForSearch' => 'standard_fields.keyword',
            'isKeywordMultiField' => false,
        ]));

        $query = $filters->getMultiSelectQuery(
            new MultiSelectFilter('keyword', [''], matchMissingForEmptyValue: true),
            null,
            $this->makeEmpty(SearchInterface::class)
        );

        $this->assertSame([
            'bool' => [
                'should' => [
                    ['bool' => ['must_not' => ['exists' => ['field' => 'standard_fields.keyword']]]],
                    ['terms' => ['standard_fields.keyword' => ['']]],
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
