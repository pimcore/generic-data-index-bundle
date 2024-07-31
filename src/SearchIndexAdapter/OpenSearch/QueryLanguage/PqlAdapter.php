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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\DependencyInjection\ServiceTag;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\ParseResultSubQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\SubQueryResultList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ProcessorInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\QueryLanguage\PqlAdapterInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * @internal
 */
final readonly class PqlAdapter implements PqlAdapterInterface
{
    public function __construct(
        private SubQueriesProcessorInterface $subQueriesProcessor,
        #[TaggedIterator(ServiceTag::PQL_FIELD_NAME_TRANSFORMER->value)]
        private iterable $fieldNameTransformers,
        #[TaggedIterator(ServiceTag::PQL_FIELD_NAME_TRANSFORMER_SORT->value)]
        private iterable $fieldNameTransformersSort,
        #[TaggedIterator(ServiceTag::PQL_FIELD_NAME_VALIDATOR->value)]
        private iterable $fieldNameValidators,
    ) {
    }

    public function translateOperatorToSearchQuery(QueryTokenType $operator, string $field, mixed $value): array
    {
        if ($this->isNullValue($value)) {
            return $this->handleNullValue($operator, $field);
        }

        if ($this->isEmptyValue($value)) {
            return $this->handleEmptyValue($operator, $field);
        }

        if ($this->isMatchComparison($operator, $field)) {
            return $this->handleMatchComparison($operator, $field, $value);
        }

        return match($operator) {
            QueryTokenType::T_EQ  => ['term' => [$field => $value]],
            QueryTokenType::T_NEQ  => ['bool' => ['must_not' => ['term' => [$field => $value]]]],
            QueryTokenType::T_GT => ['range' => [$field => ['gt' => $value]]],
            QueryTokenType::T_LT => ['range' => [$field => ['lt' => $value]]],
            QueryTokenType::T_GTE => ['range' => [$field => ['gte' => $value]]],
            QueryTokenType::T_LTE => ['range' => [$field => ['lte' => $value]]],
            QueryTokenType::T_LIKE => ['wildcard' => [$field => ['value' => $value, 'case_insensitive' => true]]],
            QueryTokenType::T_NOT_LIKE => $this->createMustNot(
                ['wildcard' => [$field => ['value' => $value, 'case_insensitive' => true]]]
            ),
            default => throw new InvalidArgumentException('Unknown operator: ' . $operator->value)
        };
    }

    private function isNullValue(mixed $value): bool
    {
        return $value === QueryTokenType::T_NULL;
    }

    private function isEmptyValue(mixed $value): bool
    {
        return $value === QueryTokenType::T_EMPTY;
    }

    private function isMatchComparison(QueryTokenType $operator, string $field): bool
    {
        return ($operator === QueryTokenType::T_EQ || $operator === QueryTokenType::T_NEQ)
            && !str_ends_with($field, '.keyword');
    }

    private function handleMatchComparison(QueryTokenType $operator, string $field, mixed $value): array
    {
        if ($operator === QueryTokenType::T_EQ) {
            return ['match' => [$field => $value]];
        }

        if ($operator === QueryTokenType::T_NEQ) {
            return $this->createMustNot(['match' => [$field => $value]]);
        }

        throw new InvalidArgumentException(
            'Invalid match comparison operator ' . $operator->value . ' for field ' . $field
        );
    }

    private function handleNullValue(QueryTokenType $operator, string $field): array
    {
        if ($operator === QueryTokenType::T_EQ) {
            return $this->createMustNot(['exists' => ['field' => $field]]);
        }

        if ($operator === QueryTokenType::T_NEQ) {
            return ['exists' => ['field' => $field]];
        }

        throw new InvalidArgumentException(
            'Operator ' . $operator->value . ' does not support for null values'
        );
    }

    private function handleEmptyValue(QueryTokenType $operator, string $field): array
    {

        return [
            'bool' => [
                'should' => [
                    $this->handleNullValue($operator, $field),
                    $this->translateOperatorToSearchQuery($operator, $field, ''),
                ],
            ],
        ];
    }

    private function createMustNot(array $query): array
    {
        return ['bool' => ['must_not' => $query]];
    }

    public function translateToQueryStringQuery(string $query): array
    {
        return ['query_string' => ['query' => $query]];
    }

    public function processSubQueries(
        ProcessorInterface $processor,
        string $originalQuery,
        array $subQueries
    ): SubQueryResultList {
        return $this->subQueriesProcessor->processSubQueries($processor, $originalQuery, $subQueries);
    }

    public function transformSubQuery(ParseResultSubQuery $subQuery, SubQueryResultList $subQueryResults): array
    {
        $field = $subQuery->getRelationFieldPath();

        return [
            'terms' => [
                $field => $subQueryResults->getSubQueryResult($subQuery->getSubQueryId()),
            ],
        ];
    }

    public function transformFieldName(
        string $fieldName,
        array $indexMapping,
        ?IndexEntity $targetEntity,
        bool $sort = false
    ): string {
        $transformers = $sort ? $this->fieldNameTransformersSort : $this->fieldNameTransformers;
        /** @var FieldNameTransformerInterface $transformer */
        foreach ($transformers as $transformer) {
            if ($transformedFieldName = $transformer->transformFieldName($fieldName, $indexMapping, $targetEntity)) {
                $fieldName = $transformedFieldName;
                if ($transformer->stopPropagation()) {
                    break;
                }
            }
        }

        return $fieldName;
    }

    public function validateFieldName(
        string $originalFieldName,
        string $fieldName,
        array $indexMapping,
        ?IndexEntity $targetEntity = null
    ): ?string {
        /** @var FieldNameValidatorInterface $validator */
        foreach ($this->fieldNameValidators as $validator) {
            $errorMessage = $validator->validateFieldName($originalFieldName, $fieldName, $indexMapping, $targetEntity);
            if ($errorMessage) {
                return $errorMessage;
            }
        }

        return null;
    }
}
