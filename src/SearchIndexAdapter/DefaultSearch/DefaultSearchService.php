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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch;

use Exception;
use JsonException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\ReindexFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\SearchFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\SwitchIndexAliasException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Debug\SearchInformation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\DefaultSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\SearchExecutionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\SearchClient\SearchClientInterface;
use Psr\Log\LogLevel;

/**
 * @internal
 */
final class DefaultSearchService implements SearchIndexServiceInterface
{
    public const INDEX_VERSION_ODD = 'odd';

    public const INDEX_VERSION_EVEN = 'even';

    use LoggerAwareTrait;

    public function __construct(
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        private readonly SearchExecutionServiceInterface $searchExecutionService,
        private readonly IndexAliasServiceInterface $indexAliasService,
        private readonly SearchClientInterface $client,
        private readonly int $reindexMaxPolls,
        private readonly int $reindexPollIntervalSeconds,
    ) {
    }

    public function refreshIndex(string $indexName): array
    {
        $this->logger->info("Refreshing index $indexName");

        return $this->client->refreshIndex(['index' => $indexName]);
    }

    public function deleteIndex($indexName, bool $silent = false): void
    {
        if (!$this->existsIndex($indexName)) {
            return;
        }

        try {
            $this->logger->log($silent ? LogLevel::DEBUG : LogLevel::INFO, "Deleting index $indexName");
            $response = $this->client->deleteIndex(['index' => $indexName]);
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

        $result = $this->client->getIndexAlias(['name' => $indexName]);
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
            // Submit reindex as async task to avoid HTTP timeout on large indices
            $response = $this->client->reIndex([
                'body' => $body,
                'wait_for_completion' => false,
            ]);

            $taskId = $response['task'] ?? null;
            if (!$taskId) {
                throw new ReindexFailedException(
                    'Reindex did not return a task ID; response: ' . json_encode($response, JSON_PARTIAL_OUTPUT_ON_ERROR)
                );
            }

            $this->waitForTask($taskId);
            $this->switchIndexAliasAndCleanup($indexName, $oldIndexName, $newIndexName);
        } catch (\Throwable $e) {
            $this->deleteIndex($newIndexName, true);

            throw $e;
        }
    }

    /**
     * @throws ReindexFailedException
     */
    private function waitForTask(string $taskId): void
    {
        for ($poll = 0; $poll < $this->reindexMaxPolls; $poll++) {
            $taskStatus = $this->fetchTaskStatus($taskId);

            if (empty($taskStatus['completed'])) {
                sleep($this->reindexPollIntervalSeconds);

                continue;
            }

            // Top-level task error (auth failure, node loss, etc.)
            if (!empty($taskStatus['error'])) {
                throw new ReindexFailedException(
                    'Reindex task failed: ' . json_encode($taskStatus['error'], JSON_PARTIAL_OUTPUT_ON_ERROR)
                );
            }

            // Per-document failures and timed_out live inside response
            $response = $taskStatus['response'] ?? [];

            if (!empty($response['timed_out'])) {
                throw new ReindexFailedException(
                    "Reindex task timed out server-side for task $taskId"
                );
            }

            if (!empty($response['failures'])) {
                throw new ReindexFailedException(
                    'Reindex task completed with failures: ' . json_encode($response['failures'], JSON_PARTIAL_OUTPUT_ON_ERROR)
                );
            }

            return;
        }

        throw new ReindexFailedException(
            \sprintf(
                'Reindex task %s did not complete within %d seconds',
                $taskId,
                $this->reindexMaxPolls * $this->reindexPollIntervalSeconds
            )
        );
    }

    /**
     * @throws ReindexFailedException
     */
    private function fetchTaskStatus(string $taskId): array
    {
        $client = $this->client;

        if (!\is_callable([$client, 'getOriginalClient'])) {
            throw new ReindexFailedException(
                'Task polling requires a client exposing getOriginalClient(); got ' . \get_class($client)
            );
        }

        try {
            $response = $client->getOriginalClient()->tasks()->get(['task_id' => $taskId]);
        } catch (\Throwable $e) {
            throw new ReindexFailedException(\sprintf('Failed to fetch status for reindex task %s', $taskId), 0, $e);
        }

        if (\is_object($response) && \method_exists($response, 'asArray')) {
            $response = $response->asArray();
        }

        if (!\is_array($response)) {
            throw new ReindexFailedException(
                'Unexpected task status response type: ' . (\is_object($response) ? \get_class($response) : \gettype($response))
            );
        }

        return $response;
    }

    public function createIndex(string $indexName, ?array $mappings = null): DefaultSearchService
    {
        $this->deleteIndex($indexName, true);

        try {
            $this->logger->info("Creating index $indexName");

            $body = [];
            if (!empty($this->searchIndexConfigService->getIndexSettings())) {
                $body['settings'] = $this->searchIndexConfigService->getIndexSettings();
            }

            if ($mappings) {
                $body['mappings']['properties'] = $mappings;
            }

            $response = $this->client->createIndex(
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

    public function addAlias(string $aliasName, string $indexName): array
    {
        return $this->indexAliasService->addAlias($aliasName, $indexName);
    }

    public function existsAlias(string $aliasName, ?string $indexName = null): bool
    {
        return $this->indexAliasService->existsAlias($aliasName, $indexName);
    }

    public function existsIndex(string $indexName): bool
    {
        return $this->client->existsIndex([
            'index' => $indexName,
            'client' => [
                'ignore' => [404],
            ],
        ]);
    }

    public function deleteAlias(string $indexName, string $aliasName): array
    {
        return $this->indexAliasService->deleteAlias($indexName, $aliasName);
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

        return $this->client->get($params);
    }

    public function putMapping(array $params): array
    {
        return $this->client->putIndexMapping($params);
    }

    public function getMapping(string $indexName): array
    {
        return $this->client->getIndexMapping(['index' => $indexName]);
    }

    public function countByAttributeValue(string $indexName, string $attribute, string $value): int
    {
        $countResult = $this->client->search([
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

    public function createPaginatedSearch(
        int $page,
        int $pageSize,
        bool $aggregationsOnly = false
    ): DefaultSearchInterface {
        if ($aggregationsOnly) {
            return new Search(
                from: 0,
                size: 0
            );
        }

        return new Search(
            from: $pageSize * ($page - 1),
            size: $pageSize
        );
    }

    /**
     * @throws SearchFailedException
     */
    public function search(
        AdapterSearchInterface $search,
        string $indexName,
        int|bool|null $trackTotalHits = true
    ): SearchResult {
        return $this->searchExecutionService->executeSearch(
            $search,
            $indexName,
            $trackTotalHits

        );
    }

    /**
     * @return SearchInformation[]
     */
    public function getExecutedSearches(): array
    {
        return $this->searchExecutionService->getExecutedSearches();
    }

    public function getStats(string $indexName): array
    {
        return $this->client->getIndexStats(['index' => $indexName]);
    }

    public function getIndexSettings(string $indexName): array
    {
        return $this->client->getIndexSettings(['index' => $indexName]);
    }

    public function getCount(AdapterSearchInterface $search, string $indexName): int
    {
        $body = $search->toArray();

        // Remove not allowed keys
        $body = array_diff_key(
            $body,
            array_flip(
                [
                    '_source',
                    'sort',
                    'from',
                    'size',
                    'aggs',
                ]
            )
        );

        $result = $this->client->count([
            'index' => $indexName,
            'body' => $body,
        ]);

        return $result['count'] ?? 0;
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
                        'index' => $aliasName . '*',
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

        $result = $this->client->updateIndexAliases($params);
        if (!$result['acknowledged']) {
            throw new SwitchIndexAliasException('Switching Alias failed for ' . $newIndexName);
        }

        // Delete both suffixes to avoid orphaned indices — skip only the newly aliased index
        foreach (['-' . self::INDEX_VERSION_EVEN, '-' . self::INDEX_VERSION_ODD] as $suffix) {
            $candidate = $aliasName . $suffix;
            if ($candidate !== $newIndexName) {
                $this->deleteIndex($candidate, true);
            }
        }

    }
}
