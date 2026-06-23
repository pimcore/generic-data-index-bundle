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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\SearchFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ReindexFailedException;
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
use Throwable;

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
        private readonly SearchClientInterface $client
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
     * Reindex from the current live index version into a freshly-created opposite version,
     * then swap the alias.
     *
     * Uses the async `_reindex` API (`wait_for_completion=false`) and polls the tasks API,
     * because synchronous `_reindex` is the canonical cause of HTTP 5xx timeouts at gateways
     * for large indices. Any failure (kickoff error, task error, document-level failures,
     * timeout) triggers cleanup of the orphaned target index and a thrown
     * {@see ReindexFailedException} whose `previous` carries the original cause.
     *
     * @throws ReindexFailedException
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

        try {
            $taskId = $this->startReindexTask($oldIndexName, $newIndexName);
            $this->waitForReindexTask($taskId, $oldIndexName, $newIndexName);
        } catch (Throwable $e) {
            // Orphan cleanup so a failed run does not leave behind an empty alternate index.
            $this->deleteIndex($newIndexName, true);

            if ($e instanceof ReindexFailedException) {
                throw $e;
            }

            throw new ReindexFailedException(
                sprintf(
                    'Reindex from "%s" to "%s" failed: %s',
                    $oldIndexName,
                    $newIndexName,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }

        $this->switchIndexAliasAndCleanup($indexName, $oldIndexName, $newIndexName);
    }

    /**
     * Kicks off an asynchronous _reindex and returns the OpenSearch / Elasticsearch task id.
     *
     * @throws ReindexFailedException
     */
    private function startReindexTask(string $oldIndexName, string $newIndexName): string
    {
        $body = [
            'source' => ['index' => $oldIndexName],
            'dest' => ['index' => $newIndexName],
        ];

        $response = $this->client->reIndex([
            'body' => $body,
            'wait_for_completion' => false,
        ]);

        if (!isset($response['task']) || !is_string($response['task']) || $response['task'] === '') {
            throw new ReindexFailedException(
                'Async _reindex did not return a task id. Response: '
                . $this->safeJsonEncode($response)
            );
        }

        return $response['task'];
    }

    /**
     * Polls the tasks API until the reindex task completes, or until the configured
     * max wait elapses. Translates task-level errors and document-level failures into
     * a ReindexFailedException so the caller can react and clean up.
     *
     * @throws ReindexFailedException
     */
    private function waitForReindexTask(string $taskId, string $oldIndexName, string $newIndexName): void
    {
        $pollInterval = max(1, $this->searchIndexConfigService->getReindexPollIntervalSeconds());
        $maxWait = max(1, $this->searchIndexConfigService->getReindexMaxWaitSeconds());
        $deadline = time() + $maxWait;

        while (true) {
            $taskInfo = $this->getTaskInfo($taskId);

            if (($taskInfo['completed'] ?? false) === true) {
                if (!empty($taskInfo['error'])) {
                    throw new ReindexFailedException(sprintf(
                        'Reindex task "%s" completed with error: %s',
                        $taskId,
                        $this->safeJsonEncode($taskInfo['error'])
                    ));
                }

                $failures = $taskInfo['response']['failures'] ?? [];
                if (!empty($failures)) {
                    throw new ReindexFailedException(sprintf(
                        'Reindex task "%s" completed with %d document failure(s): %s',
                        $taskId,
                        count($failures),
                        $this->safeJsonEncode(array_slice($failures, 0, 5))
                    ));
                }

                $this->logger->info(sprintf(
                    'Reindex task "%s" completed (old="%s", new="%s", took=%sms, total=%s)',
                    $taskId,
                    $oldIndexName,
                    $newIndexName,
                    $taskInfo['response']['took'] ?? 'n/a',
                    $taskInfo['response']['total'] ?? 'n/a'
                ));

                return;
            }

            if (time() > $deadline) {
                throw new ReindexFailedException(sprintf(
                    'Reindex task "%s" did not complete within %d seconds (old="%s", new="%s").',
                    $taskId,
                    $maxWait,
                    $oldIndexName,
                    $newIndexName
                ));
            }

            sleep($pollInterval);
        }
    }

    /**
     * Pimcore's shared SearchClientInterface does not expose the tasks API, but both
     * OpenSearch's OpenSearch\Client and Elasticsearch's Elastic\Elasticsearch\Client
     * expose `->tasks()->get(['task_id' => ...])`. Reach for the original client via
     * the bundle-local sub-interfaces that expose getOriginalClient().
     *
     * Protected so unit tests can supply task responses without stubbing the entire
     * underlying client SDK.
     *
     * @return array<string,mixed>
     *
     * @throws ReindexFailedException
     */
    protected function getTaskInfo(string $taskId): array
    {
        if (!method_exists($this->client, 'getOriginalClient')) {
            throw new ReindexFailedException(
                'Configured search client does not expose the tasks API; cannot poll reindex task "' . $taskId . '".'
            );
        }

        try {
            // @phpstan-ignore-next-line method.notFound (only OpenSearch/Elasticsearch sub-interfaces expose getOriginalClient; guarded above)
            $originalClient = $this->client->getOriginalClient();
            $raw = $originalClient->tasks()->get(['task_id' => $taskId]);
        } catch (Throwable $e) {
            throw new ReindexFailedException(
                'Failed to poll reindex task "' . $taskId . '": ' . $e->getMessage(),
                0,
                $e
            );
        }

        if (is_object($raw) && method_exists($raw, 'asArray')) {
            return $raw->asArray();
        }

        return is_array($raw) ? $raw : [];
    }

    private function safeJsonEncode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return '(unencodable)';
        }
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

        $this->deleteIndex($oldIndexName);
    }
}
