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
        #[TaggedIterator(ServiceTag::PQL_FIELD_NAME_VALIDATOR->value)]
        private iterable $fieldNameValidators,
    ) {
    }

    public function translateOperatorToSearchQuery(QueryTokenType $operator, string $field, mixed $value): array
    {
        // term query works for keyword fields only
        if ($operator === QueryTokenType::T_EQ && !str_ends_with($field, '.keyword')) {
            return ['match' => [$field => $value]];
        }

        return match($operator) {
            QueryTokenType::T_EQ  => ['term' => [$field => $value]],
            QueryTokenType::T_GT => ['range' => [$field => ['gt' => $value]]],
            QueryTokenType::T_LT => ['range' => [$field => ['lt' => $value]]],
            QueryTokenType::T_GTE => ['range' => [$field => ['gte' => $value]]],
            QueryTokenType::T_LTE => ['range' => [$field => ['lte' => $value]]],
            QueryTokenType::T_LIKE => ['wildcard' => [$field => ['value' => $value, 'case_insensitive' => true]]],
            default => throw new InvalidArgumentException('Unknown operator: ' . $operator->value)
        };
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

    public function transformFieldName(string $fieldName, array $indexMapping, ?IndexEntity $targetEntity): string
    {
        /** @var FieldNameTransformerInterface $transformer */
        foreach ($this->fieldNameTransformers as $transformer) {
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
