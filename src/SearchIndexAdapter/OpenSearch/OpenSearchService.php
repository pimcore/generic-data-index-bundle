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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch;

use Exception;
use JsonException;
use OpenSearch\Client;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\SwitchIndexAliasException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultAggregationBucket;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultHit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Psr\Log\LogLevel;

/**
 * @internal
 */
final class OpenSearchService implements SearchIndexServiceInterface
{
    private const INDEX_VERSION_ODD = 'odd';

    private const INDEX_VERSION_EVEN = 'even';

    use LoggerAwareTrait;

    public function __construct(
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        private readonly Client $openSearchClient,
    ) {
    }

    public function refreshIndex(string $indexName): array
    {
        $this->logger->info("Refreshing index $indexName");

        return $this->openSearchClient
            ->indices()
            ->refresh(['index' => $indexName]);
    }

    public function deleteIndex($indexName, bool $silent = false): void
    {
        if (!$this->existsIndex($indexName)) {
            return;
        }

        try {
            $this->logger->log($silent ? LogLevel::DEBUG : LogLevel::INFO, "Deleting index $indexName");
            $response = $this->openSearchClient->indices()->delete(['index' => $indexName]);
            $this->logger->debug(json_encode($response, JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            $this->logger->debug('Error while parsing json response: ' . $indexName . ' ' . $e);
        }
    }

    public function getCurrentIndexVersion(string $indexName): string
    {
        if (!$this->existsAlias($indexName)) {
            return '';
        }

        $result = $this->openSearchClient->indices()->getAlias([
            'name' => $indexName,
        ]);

        if (empty($result)) {
            return '';
        }

        $aliasIndexName = array_key_first($result);
        $nameParts = explode('-', $aliasIndexName);

        return end($nameParts);
    }

    /**
     * @throws Exception
     */
    public function reindex(string $indexName, array $mapping): void
    {
        $currentIndexVersion = $this->getCurrentIndexVersion($indexName);
        $newIndexVersion = $currentIndexVersion === self::INDEX_VERSION_EVEN
            ? self::INDEX_VERSION_ODD
            : self::INDEX_VERSION_EVEN;

        $oldIndexName = $indexName . '-' . $currentIndexVersion;
        $newIndexName = $indexName . '-' . $newIndexVersion;

        $this->createIndex($newIndexName, $mapping);

        $body = [
            'source' => [
                'index' => $oldIndexName,

            ],
            'dest' => [
                'index' => $newIndexName,
            ],
        ];

        try {
            $this->openSearchClient->reindex([
                'body' => $body,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Reindexing failed due to following error: ' . $e);
        }

        $this->switchIndexAliasAndCleanup($indexName, $oldIndexName, $newIndexName);
    }

    public function createIndex(string $indexName, array $mappings = null): OpenSearchService
    {
        $this->deleteIndex($indexName, true);

        try {
            $this->logger->info("Creating index $indexName");

            $body = [];
            if(!empty($this->searchIndexConfigService->getIndexSettings())) {
                $body['settings'] = $this->searchIndexConfigService->getIndexSettings();
            }

            if ($mappings) {
                $body['mappings']['properties'] = $mappings;
            }

            $response = $this->openSearchClient->indices()->create(
                [
                    'index' => $indexName,
                    'body' => $body,
                ]
            );

            $this->logger->debug(json_encode($response, JSON_THROW_ON_ERROR));
        } catch (Exception $e) {
            $this->logger->error($e);
        }

        return $this;
    }

    public function addAlias(string $aliasName, string $indexName): OpenSearchService
    {
        $params['body'] = [
            'actions' => [
                [
                    'add' => [
                        'index' => $indexName,
                        'alias' => $aliasName,
                    ],
                ],
            ],
        ];
        $this->openSearchClient->indices()->updateAliases($params);

        return $this;
    }

    public function putAlias(string $aliasName, string $indexName): array
    {
        return $this->openSearchClient->indices()->putAlias([
            'name' => $aliasName,
            'index' => $indexName,
        ]);
    }

    public function existsAlias(string $aliasName, string $indexName = null): bool
    {
        return $this->openSearchClient->indices()->existsAlias([
            'name' => $aliasName,
            'index' => $indexName,
            'client' => [
                'ignore' => [404],
            ],
        ]);
    }

    public function existsIndex(string $indexName): bool
    {
        return $this->openSearchClient->indices()->exists([
            'index' => $indexName,
            'client' => [
                'ignore' => [404],
            ],
        ]);
    }

    public function deleteAlias(string $indexName, string $aliasName): array
    {
        return $this->openSearchClient->indices()->deleteAlias([
            'name' => $aliasName,
            'index' => $indexName,
        ]);
    }

    public function getDocument(string $index, int $id, bool $ignore404 = false): array
    {
        $params = [
            'index' => $index,
            'id' => $id,
        ];

        if ($ignore404) {
            $params['client'] = [
                'ignore' => [404],
            ];
        }

        return $this->openSearchClient->get($params);
    }

    public function putMapping(array $params): array
    {
        return $this->openSearchClient->indices()->putMapping($params);
    }

    public function countByAttributeValue(string $indexName, string $attribute, string $value): int
    {
        $countResult = $this->openSearchClient->search([
            'index' => $indexName,
            'track_total_hits' => true,
            'rest_total_hits_as_int' => true,
            'body' => [
                'query' => [
                    'term' => [
                        $attribute => $value,
                    ],
                ],
                'size' => 0,
            ],
        ]);

        return $countResult['hits']['total'] ?? 0;
    }

    public function createPaginatedSearch(int $page, int $pageSize): AdapterSearchInterface
    {
        return new Search(
            from: $pageSize * ($page - 1),
            size: $pageSize
        );
    }

    public function search(AdapterSearchInterface $search, string $indexName): SearchResult
    {
        $openSearchResult = $this
            ->openSearchClient
            ->search([
                'index' => $indexName,
                'body' => $search->toArray(),
            ]);

        return new SearchResult(
            hits: $this->hydrateHits($openSearchResult['hits']['hits']),
            aggregations: $this->hydrateAggregations($openSearchResult['hits']['aggregations'] ?? []),
            totalHits: $openSearchResult['hits']['total']['value'],
            maxScore: $openSearchResult['hits']['max_score'],
        );
    }

    /**
     * @return SearchResultHit[]
     */
    private function hydrateHits(array $hits): array
    {
        $result = [];

        foreach ($hits as $hit) {
            $result[] = new SearchResultHit(
                id: $hit['_id'],
                index: $hit['_index'],
                score: $hit['_score'],
                source: $hit['_source'],
            );
        }
        return $result;
    }

    /**
     * @return SearchResultAggregation[]
     */
    private function hydrateAggregations(array $aggregations): array
    {
        $result = [];
        foreach ($aggregations as $name => $aggregation) {
            $result[] = new SearchResultAggregation(
                name: $name,
                buckets: $this->hydrateAggregationBuckets($aggregation['buckets'] ?? []),
                otherDocCount: $aggregation['sum_other_doc_count'] ?? 0,
                docCountErrorUpperBound: $aggregation['doc_count_error_upper_bound'] ?? 0,
            );
        }

        return $result;
    }

    /**
     * @return SearchResultAggregationBucket[]
     */
    private function hydrateAggregationBuckets(array $buckets): array
    {
        $result = [];
        foreach ($buckets as $bucket) {
            $result[] = new SearchResultAggregationBucket(
                key: $bucket['key'],
                docCount: $bucket['doc_count'],
            );
        }
        return $result;
    }

    public function getOpenSearchClient(): Client
    {
        return $this->openSearchClient;
    }

    /**
     * @throws SwitchIndexAliasException
     */
    private function switchIndexAliasAndCleanup(string $aliasName, string $oldIndexName, string $newIndexName): void
    {
        $params['body'] = [
            'actions' => [
                [
                    'remove' => [
                        'index' => '*',
                        'alias' => $aliasName,
                    ],
                ],
                [
                    'add' => [
                        'index' => $newIndexName,
                        'alias' => $aliasName,
                    ],
                ],
            ],
        ];
        $result = $this->openSearchClient->indices()->updateAliases($params);
        if (!$result['acknowledged']) {
            throw new SwitchIndexAliasException('Switching Alias failed for ' . $newIndexName);
        }

        $this->deleteIndex($oldIndexName);
    }
}
