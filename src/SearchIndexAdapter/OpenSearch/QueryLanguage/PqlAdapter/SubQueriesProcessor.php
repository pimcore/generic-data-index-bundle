<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\QueryLanguage\PqlAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\QueryLanguage\ParsingException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\SubQueryResultList;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ProcessorInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\FetchIdsBySearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexEntityServiceInterface;

/**
 * @internal
 */
final readonly class SubQueriesProcessor implements SubQueriesProcessorInterface
{
    public function __construct(
        private IndexEntityServiceInterface $indexEntityService,
        private SearchIndexServiceInterface $searchIndexService,
        private FetchIdsBySearchServiceInterface $fetchIdsBySearchService,
    )
    {

    }

    public function processSubQueries(
        ProcessorInterface $processor,
        string $originalQuery,
        array $subQueries
    ): SubQueryResultList
    {
        $list = new SubQueryResultList();
        foreach ($subQueries as $subQuery) {

            $indexEntity = $this->indexEntityService->getByEntityName($subQuery->getTargetType());

            if (!$this->searchIndexService->existsAlias($indexEntity->getIndexName())) {
                throw new ParsingException(
                    $originalQuery,
                    'a valid entity name',
                    '`' . $subQuery->getTargetType(). '`',
                    null,
                    null,
                    $subQuery->getPositionInOriginalQuery() - strlen($subQuery->getTargetType()) - 1,
                );
            }

            try {
                $query = $processor->process(
                    $subQuery->getTargetQuery(),
                    $indexEntity,
                );
            } catch(ParsingException $e) {
                throw new ParsingException(
                    $originalQuery,
                    $e->getExpected(),
                    $e->getFound(),
                    $e->getToken(),
                    $e->getMessage(),
                    $subQuery->getPositionInOriginalQuery() + $e->getPosition(),
                    $e
                );
            }

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

}