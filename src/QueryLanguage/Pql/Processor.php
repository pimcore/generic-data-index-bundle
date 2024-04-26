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

namespace Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\Pql;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\OpenSearch\ConditionType;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Query\BoolQuery;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\QueryLanguage\ParseResultSubQuery;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\LexerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ParserInterface;
use Pimcore\Bundle\GenericDataIndexBundle\QueryLanguage\ProcessorInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search\FetchIdsBySearchServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final class Processor implements ProcessorInterface
{
    public function __construct(
        private readonly LexerInterface $lexer,
        private readonly ParserInterface $parser,
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        private readonly FetchIdsBySearchServiceInterface $fetchIdsBySearchService,
    ) {
    }

    public function process(string $query): array
    {

        $this->lexer->setQuery($query);
        $tokens = $this->lexer->getTokens();

        $parseResult = $this->parser
            ->applyTokens($tokens)
            ->parse();

        $result = $parseResult->getQuery();

        $subQueryResults = [];
        foreach ($parseResult->getSubQueries() as $subQuery) {
            $subQueryResults[$subQuery->getSubQueryId()] =  $this->processSubQuery($subQuery);
        }

        array_walk_recursive(
            $result,
            static function (&$value) use($subQueryResults) {
                if ($value instanceof ParseResultSubQuery)  {
                    $value = [
                        'terms' => [
                            $value->getRelationFieldPath() => $subQueryResults[$value->getSubQueryId()] ?? []
                        ]
                    ];
                }
            }
        );

        return $result;
    }

    private function processSubQuery(ParseResultSubQuery $subQuery): array
    {
        $query = $this->process($subQuery->getTargetQuery());

        $search = new Search();
        $search
            ->addQuery(new BoolQuery([ConditionType::FILTER->value => $query]))
        ;

        return $this->fetchIdsBySearchService->fetchAllIds($search, $this->getIndexNameFromEntityName($subQuery->getTargetType()));
    }

    private function getIndexNameFromEntityName(string $entityName): string
    {
        return $this->searchIndexConfigService->getIndexName($entityName);
    }
}
