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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\DependencyInjection\ServiceTag;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\QueryLanguage\QueryTokenType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\InvalidArgumentException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\ParseResultSubQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\SubQueryResultList;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\IndexEntity;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ProcessorInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\FetchIdsBySearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\QueryLanguage\PqlAdapterInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexEntityServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * @internal
 */
final readonly class PqlAdapter implements PqlAdapterInterface
{
    public function __construct(
        private IndexEntityServiceInterface $indexEntityService,
        private FetchIdsBySearchServiceInterface $fetchIdsBySearchService,
        #[TaggedIterator(ServiceTag::PQL_FIELD_NAME_TRANSFORMER->value)]
        private iterable $fieldNameTransformers,
    ) {
    }

    public function translateOperatorToSearchQuery(QueryTokenType $operator, string $field, mixed $value): array
    {
        return match($operator) {
            QueryTokenType::T_EQ => ['match' => [$field => $value]],
            QueryTokenType::T_GT => ['range' => [$field => ['gt' => $value]]],
            QueryTokenType::T_LT => ['range' => [$field => ['lt' => $value]]],
            QueryTokenType::T_GTE => ['range' => [$field => ['gte' => $value]]],
            QueryTokenType::T_LTE => ['range' => [$field => ['lte' => $value]]],
            QueryTokenType::T_LIKE => ['wildcard' => [$field => str_replace('*', '?', $value)]],
            default => throw new InvalidArgumentException('Unknown operator: ' . $operator->value)
        };
    }

    public function translateToQueryStringQuery(string $query): array
    {
        return ['query_string' => ['query' => $query]];
    }

    public function processSubQueries(ProcessorInterface $processor, array $subQueries): SubQueryResultList
    {
        $list = new SubQueryResultList();
        foreach ($subQueries as $subQuery) {

            $indexEntity = $this->indexEntityService->getByEntityName($subQuery->getTargetType());

            $query = $processor->process(
                $subQuery->getTargetQuery(),
                $indexEntity,
            );

            $search = new Search();
            $search
                ->addQuery(new BoolQuery([ConditionType::FILTER->value => $query]));

            $list->addResult(
                $subQuery->getSubQueryId(),
                $this->fetchIdsBySearchService->fetchAllIds(
                    $search,
                    $indexEntity->getIndexName()
                )
            );
        }

        return $list;
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

    public function transformFieldName(string $fieldName, IndexEntity $indexEntity, array $indexMapping): string
    {
        /** @var FieldNameTransformerInterface $transformer */
        foreach($this->fieldNameTransformers as $transformer) {
            if ($transformedFieldName = $transformer->transformFieldName($fieldName, $indexEntity, $indexMapping)) {
                $fieldName = $transformedFieldName;
                if ($transformer->stopPropagation()) {
                    break;
                }
            }
        }

        return $fieldName;
    }
}
