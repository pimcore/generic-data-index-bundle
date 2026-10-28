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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ReindexResult;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\DocumentCountFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\ReindexFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\SearchFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\SwitchIndexAliasException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Debug\SearchInformation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\DefaultSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Reindex\ReindexOutcome;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Reindex\ReindexStripScript;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Processor\SearchBodyProcessorPipeline;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\SearchExecutionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\SearchClient\SearchClientInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

/**
 * @internal
 */
final class DefaultSearchService implements SearchIndexServiceInterface
{
    public const INDEX_VERSION_ODD = 'odd';

    public const INDEX_VERSION_EVEN = 'even';

    private const MAX_CONSECUTIVE_POLL_FAILURES = 3;

    /** Log the progress of a running copy every n polls (one minute with the default poll interval). */
    private const PROGRESS_LOG_EVERY_POLLS = 12;

    /**
     * Failure cause types that prove the existing documents are structurally
     * incompatible with the new mapping. Anything else (rejected executions,
     * cluster blocks, unavailable shards, ...) may be transient and must never
     * be answered with an index recreation.
     */
    private const MAPPING_INCOMPATIBILITY_FAILURE_TYPES = [
        'mapper_parsing_exception',
        'mapper_exception',
        'strict_dynamic_mapping_exception',
        'document_parsing_exception',
    ];

    /**
     * OpenSearch reports a term over 32766 bytes in a keyword field as illegal_argument_exception;
     * it is caused by the data like a parsing failure, never transient.
     */
    private const IMMENSE_TERM_MARKER = 'immense term in field="';

    /** Reasons that name the failing field; the first capture group is its full dotted path. */
    private const FAILING_FIELD_PATTERNS = [
        '/failed to parse field \[([^\]]+)\]/',
        '/object mapping for \[([^\]]+)\]/',
        '/Could not dynamically add mapping for field \[([^\]]+)\]/',
        '/immense term in field="([^"]+)"/',
    ];

    /** "dynamic introduction of [name] within [parent.path] is not allowed" */
    private const STRICT_DYNAMIC_FIELD_PATTERN = '/dynamic introduction of \[([^\]]+)\] within \[([^\]]+)\]/';

    private const MAX_CAUSE_DEPTH = 5;

    private const ROOT_OBJECT_NAME = '_doc';

    use LoggerAwareTrait;

    public function __construct(
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        private readonly SearchExecutionServiceInterface $searchExecutionService,
        private readonly IndexAliasServiceInterface $indexAliasService,
        private readonly SearchClientInterface $client,
        private readonly int $reindexMaxPolls,
        private readonly int $reindexPollIntervalSeconds,
        private readonly SearchBodyProcessorPipeline $searchBodyProcessorPipeline = new SearchBodyProcessorPipeline(),
    ) {
        if (!isset($this->logger)) {
            $this->logger = new NullLogger();
        }
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
    public function reindex(string $indexName, array $mapping): ReindexResult
    {
        return $this->copyIntoNewIndex($indexName, $mapping, null)->getResult();
    }

    public function reindexWithoutFields(string $indexName, array $mapping, array $stripPaths): ReindexOutcome
    {
        return $this->copyIntoNewIndex(
            $indexName,
            $mapping,
            $stripPaths === [] ? null : new ReindexStripScript($stripPaths)
        );
    }

    /**
     * @throws Exception
     */
    private function copyIntoNewIndex(string $indexName, array $mapping, ?ReindexStripScript $script): ReindexOutcome
    {
        $outcome = $this->tryCopyIntoNewIndex($indexName, $mapping, $script);
        if ($outcome !== null) {
            return $outcome;
        }

        // Another copy of the same index finished first and switched the alias to the index this one was writing
        // into. Its result may carry another mapping, so this mapping is applied on top of it with a second copy.
        $this->logger->notice(sprintf(
            'Another reindex of "%s" finished first; copying again from its result',
            $indexName
        ));
        $outcome = $this->tryCopyIntoNewIndex($indexName, $mapping, $script);
        if ($outcome !== null) {
            return $outcome;
        }

        throw new ReindexFailedException(sprintf(
            'Another reindex of "%s" finished first, twice in a row; the alias is intact, retry once the index is idle',
            $indexName
        ));
    }

    /**
     * One odd/even copy. Null means the copy lost a race: another copy of the same index switched the alias to the
     * index this one was writing into. That index is live now and is never deleted; the caller copies again from it.
     *
     * @throws Exception
     */
    private function tryCopyIntoNewIndex(
        string $indexName,
        array $mapping,
        ?ReindexStripScript $script
    ): ?ReindexOutcome {
        $currentIndexVersion = $this->getCurrentIndexVersion($indexName);
        $newIndexVersion = $currentIndexVersion === self::INDEX_VERSION_EVEN
            ? self::INDEX_VERSION_ODD
            : self::INDEX_VERSION_EVEN;

        $oldIndexName = $indexName . '-' . $currentIndexVersion;
        $newIndexName = $indexName . '-' . $newIndexVersion;

        $this->ensureNoActiveReindexTask($newIndexName);
        if ($this->aliasPointsTo($indexName, $newIndexName)) {
            // The alias moved since the version was read; the target is the live index.
            return null;
        }
        $this->deleteIndex($newIndexName, true);

        try {
            $this->doCreateIndex($newIndexName, $mapping);
        } catch (Exception $e) {
            // The reindex would otherwise auto-create the target with dynamic mapping and default settings.
            throw new ReindexFailedException(sprintf('Creating index "%s" failed', $newIndexName), 0, $e);
        }

        $body = [
            'source' => [
                'index' => $oldIndexName,
            ],
            'dest' => [
                'index' => $newIndexName,
            ],
        ];
        if ($script !== null) {
            $body['script'] = $script->toRequestScript();
        }

        $taskId = null;

        try {
            // Submit reindex as async task to avoid HTTP timeout on large indices
            $response = $this->client->reIndex([
                'body' => $body,
                'wait_for_completion' => false,
            ]);

            $taskId = $response['task'] ?? null;
            if (!$taskId) {
                throw new ReindexFailedException(
                    'Reindex did not return a task ID; response: '
                    . json_encode($response, JSON_PARTIAL_OUTPUT_ON_ERROR)
                );
            }

            $taskStatus = $this->waitForTaskCompletion($taskId);
        } catch (\Throwable $e) {
            $cancelled = $this->cancelTask($taskId);
            if ($this->aliasPointsTo($indexName, $newIndexName)) {
                $this->logLostRace($indexName, $newIndexName, $e->getMessage());

                return null;
            }
            if ($cancelled) {
                $this->deleteIndex($newIndexName, true);
            } else {
                $this->logger->warning(sprintf(
                    'Keeping index "%s": %s may still write into it. The index is cleaned up by the next reindex.',
                    $newIndexName,
                    $taskId
                        ? sprintf('reindex task %s could not be confirmed as cancelled and', $taskId)
                        : 'the reindex submission was not confirmed, so an unknown task'
                ));
            }

            throw $e;
        }

        if ($this->aliasPointsTo($indexName, $newIndexName)) {
            // Whatever the task reports (cancelled, failures, or a clean copy of a source that vanished
            // meanwhile), the target is the live index now.
            $this->logLostRace($indexName, $newIndexName, 'the reindex task ' . $taskId . ' completed');

            return null;
        }

        $this->assertTaskNotCancelled($taskId, $taskStatus, $oldIndexName, $newIndexName);

        // The task is completed at this point, so the new index can be deleted safely.
        $failures = $taskStatus['response']['failures'] ?? [];
        if (!empty($failures)) {
            $this->deleteIndex($newIndexName, true);

            // Only failures that prove a structural mapping incompatibility (e.g.
            // after a field type change) are an expected outcome the caller may
            // answer with an index recreation. Everything else may be transient.
            if ($this->areMappingIncompatibilityFailures($failures)) {
                $this->logger->warning(sprintf(
                    'Reindex from "%s" to "%s" is not possible with the existing documents: %s',
                    $oldIndexName,
                    $newIndexName,
                    json_encode(array_slice($failures, 0, 5), JSON_PARTIAL_OUTPUT_ON_ERROR)
                ));

                return new ReindexOutcome(
                    ReindexResult::MAPPING_INCOMPATIBLE,
                    $this->extractFailingFieldPaths($failures)
                );
            }

            throw new ReindexFailedException(
                'Reindex task completed with failures: '
                . json_encode(array_slice($failures, 0, 5), JSON_PARTIAL_OUTPUT_ON_ERROR)
            );
        }

        $this->switchIndexAliasAndCleanup($indexName, $oldIndexName, $newIndexName);

        return new ReindexOutcome(ReindexResult::SUCCESS);
    }

    /**
     * Field paths named in parsing failures. OpenSearch and Elasticsearch name the full dotted
     * path of the field ("failed to parse field [a.b.c] of type [float] ..."), or of the object
     * for object/scalar conflicts ("object mapping for [a.b] tried to parse field [b] as object").
     * The engine reports only the first failing field per document, and only for one batch.
     *
     * @param array<int, array<string, mixed>> $failures
     *
     * @return string[][]
     */
    private function extractFailingFieldPaths(array $failures): array
    {
        $paths = [];
        foreach ($failures as $failure) {
            $path = $this->findFailingFieldPath($failure['cause'] ?? []);
            if ($path !== null) {
                $paths[implode('.', $path)] = $path;
            }
        }

        return array_values($paths);
    }

    /**
     * The field named in a failure reason, looking into the wrapped causes too: the engine wraps
     * exceptions thrown while parsing a document ("failed to parse") and names the field inside.
     *
     * @return string[]|null
     */
    private function findFailingFieldPath(array $cause): ?array
    {
        for ($depth = 0; $depth < self::MAX_CAUSE_DEPTH && $cause !== []; $depth++) {
            $reason = (string) ($cause['reason'] ?? '');
            foreach (self::FAILING_FIELD_PATTERNS as $pattern) {
                if (preg_match($pattern, $reason, $match) === 1) {
                    return explode('.', $match[1]);
                }
            }
            if (preg_match(self::STRICT_DYNAMIC_FIELD_PATTERN, $reason, $match) === 1) {
                // "_doc" names the document root, not a key in the document.
                $parent = $match[2] === self::ROOT_OBJECT_NAME ? [] : explode('.', $match[2]);

                return [...$parent, $match[1]];
            }
            $cause = is_array($cause['caused_by'] ?? null) ? $cause['caused_by'] : [];
        }

        return null;
    }

    /**
     * Polls the task status until the task completes. Transient failures of single
     * status requests are retried — a long-running reindex puts load on the cluster,
     * so occasional rejected or timed-out requests are expected and must not abort
     * the operation.
     *
     * @return array<string, mixed> the completed task status
     *
     * @throws ReindexFailedException
     */
    private function waitForTaskCompletion(string $taskId): array
    {
        $consecutivePollFailures = 0;

        for ($poll = 0; $poll < $this->reindexMaxPolls; $poll++) {
            try {
                $taskStatus = $this->fetchTaskStatus($taskId);
                $consecutivePollFailures = 0;
            } catch (ReindexFailedException $e) {
                $this->logPollFailure($taskId, ++$consecutivePollFailures, $e);
                sleep($this->reindexPollIntervalSeconds);

                continue;
            }

            if (!empty($taskStatus['completed'])) {
                return $this->assertTaskSucceeded($taskId, $taskStatus);
            }

            if ($poll > 0 && $poll % self::PROGRESS_LOG_EVERY_POLLS === 0) {
                $this->logProgress($taskId, $taskStatus, $poll);
            }
            sleep($this->reindexPollIntervalSeconds);
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
     * Whether the alias points to the given index: then it is the live index, whatever a copy into it reports.
     */
    private function aliasPointsTo(string $aliasName, string $indexName): bool
    {
        return $this->existsAlias($aliasName, $indexName);
    }

    private function logLostRace(string $aliasName, string $newIndexName, string $outcome): void
    {
        $this->logger->warning(sprintf(
            'Keeping index "%s": the alias "%s" points to it, another reindex finished first (%s)',
            $newIndexName,
            $aliasName,
            $outcome
        ));
    }

    /**
     * A cancelled task reports completed without an error and without failures; only response.canceled says so.
     * It was stopped from outside: by an operator, or by a second reindex of the same index, which then owns the
     * new index and is writing into it. Neither switch the alias to the incomplete index nor delete it.
     *
     * @param array<string, mixed> $taskStatus a completed task
     *
     * @throws ReindexFailedException when the task was cancelled
     */
    private function assertTaskNotCancelled(
        string $taskId,
        array $taskStatus,
        string $oldIndexName,
        string $newIndexName
    ): void {
        $cancelReason = $taskStatus['response']['canceled'] ?? null;
        if (!is_string($cancelReason) || $cancelReason === '') {
            return;
        }

        $this->logger->warning(sprintf(
            'Reindex task %s into "%s" was cancelled (%s); the alias stays on "%s"',
            $taskId,
            $newIndexName,
            $cancelReason,
            $oldIndexName
        ));

        throw new ReindexFailedException(sprintf('Reindex task %s was cancelled: %s', $taskId, $cancelReason));
    }

    /**
     * @throws ReindexFailedException the poll failure itself, once too many failed in a row
     */
    private function logPollFailure(string $taskId, int $consecutivePollFailures, ReindexFailedException $e): void
    {
        if ($consecutivePollFailures >= self::MAX_CONSECUTIVE_POLL_FAILURES) {
            throw $e;
        }

        $this->logger->warning(sprintf(
            'Failed to fetch status of reindex task %s (attempt %d of %d): %s',
            $taskId,
            $consecutivePollFailures,
            self::MAX_CONSECUTIVE_POLL_FAILURES,
            $e->getMessage()
        ));
    }

    /**
     * @param array<string, mixed> $taskStatus a completed task
     *
     * @return array<string, mixed> the same status
     *
     * @throws ReindexFailedException when the task reports an error or a server-side timeout
     */
    private function assertTaskSucceeded(string $taskId, array $taskStatus): array
    {
        // Top-level task error (auth failure, node loss, etc.)
        if (!empty($taskStatus['error'])) {
            throw new ReindexFailedException(
                'Reindex task failed: ' . json_encode($taskStatus['error'], JSON_PARTIAL_OUTPUT_ON_ERROR)
            );
        }

        if (!empty($taskStatus['response']['timed_out'])) {
            throw new ReindexFailedException(
                "Reindex task timed out server-side for task $taskId"
            );
        }

        return $taskStatus;
    }

    /**
     * @param array<int, array<string, mixed>> $failures
     */
    private function areMappingIncompatibilityFailures(array $failures): bool
    {
        foreach ($failures as $failure) {
            $causeType = $failure['cause']['type'] ?? '';
            $reason = (string) ($failure['cause']['reason'] ?? '');
            $isImmenseTerm = $causeType === 'illegal_argument_exception'
                && str_contains($reason, self::IMMENSE_TERM_MARKER);
            if (!$isImmenseTerm && !\in_array($causeType, self::MAPPING_INCOMPATIBILITY_FAILURE_TYPES, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cancels a server-side task so an aborted reindex does not keep writing into
     * its target index. Returns whether the task is confirmed to no longer write.
     * Only then is it safe to delete the target index.
     */
    private function cancelTask(?string $taskId): bool
    {
        if (!$taskId) {
            // The submission may have been accepted server-side even though its
            // response never arrived — a task might be running whose ID is unknown.
            return false;
        }

        if (!\is_callable([$this->client, 'getOriginalClient'])) {
            return false;
        }

        try {
            $this->client->getOriginalClient()->tasks()->cancel(['task_id' => $taskId]);
        } catch (\Throwable $e) {
            if ($this->exceptionIndicatesMissingTask($e)) {
                // the task already finished — it cannot write anymore
                return true;
            }

            $this->logger->warning(sprintf('Failed to cancel reindex task %s: %s', $taskId, $e->getMessage()));

            return false;
        }

        // Cancellation is cooperative: the task stops between batches. Report the
        // target as safe to delete only once the task is observed gone or completed.
        for ($attempt = 0; $attempt < self::MAX_CONSECUTIVE_POLL_FAILURES; $attempt++) {
            try {
                $taskStatus = $this->fetchTaskStatus($taskId);
            } catch (ReindexFailedException $e) {
                return $this->exceptionIndicatesMissingTask($e);
            }

            if (!empty($taskStatus['completed'])) {
                return true;
            }

            sleep($this->reindexPollIntervalSeconds);
        }

        return false;
    }

    public function countDocuments(string $indexName, array $query = []): int
    {
        $params = ['index' => $indexName];
        if ($query !== []) {
            $params['body'] = ['query' => $query];
        }

        $response = $this->client->count($params);
        // HTTP 200 with failed shards is a partial count; the status must not take it for the whole index.
        $failedShards = (int) ($response['_shards']['failed'] ?? 0);
        if ($failedShards > 0) {
            throw new DocumentCountFailedException(sprintf(
                'Counting the documents of "%s" failed: %d shard(s) failed, the count is partial',
                $indexName,
                $failedShards
            ));
        }
        if (!isset($response['count'])) {
            throw new DocumentCountFailedException(
                sprintf('Counting the documents of "%s" returned no count', $indexName)
            );
        }

        return (int) $response['count'];
    }

    public function getReindexTasks(): array
    {
        if (!\is_callable([$this->client, 'getOriginalClient'])) {
            // Without the tasks API no asynchronous copy can be started (see ensureNoActiveReindexTask), so none
            // is running. A failing tasks API, on the other hand, leaves the copy state unknown and throws.
            return [];
        }

        $tasks = $this->listReindexTasks();

        $result = [];
        foreach ($tasks['nodes'] ?? [] as $node) {
            foreach ($node['tasks'] ?? [] as $taskId => $task) {
                $description = (string) ($task['description'] ?? '');
                if (!preg_match('/reindex from \\[([^\\]]+)\\] to \\[([^\\]]+)\\]/', $description, $matches)) {
                    continue;
                }
                $status = $task['status'] ?? [];
                $result[] = [
                    'id' => (string) $taskId,
                    'source' => $matches[1],
                    'target' => $matches[2],
                    'created' => (int) ($status['created'] ?? 0) + (int) ($status['updated'] ?? 0),
                    'total' => (int) ($status['total'] ?? 0),
                ];
            }
        }

        return $result;
    }

    /**
     * The raw tasks API response for running reindex tasks.
     *
     * @return array<string, mixed>
     *
     * @throws ReindexFailedException
     */
    private function listReindexTasks(): array
    {
        if (!\is_callable([$this->client, 'getOriginalClient'])) {
            throw new ReindexFailedException(
                'The configured search client does not expose the tasks API'
                . ' (getOriginalClient()); the asynchronous reindex cannot be tracked.'
            );
        }

        try {
            $tasks = $this->client->getOriginalClient()->tasks()->list([
                'actions' => '*reindex',
                'detailed' => true,
            ]);

            if (\is_object($tasks) && \method_exists($tasks, 'asArray')) {
                $tasks = $tasks->asArray();
            }
        } catch (\Throwable $e) {
            throw new ReindexFailedException($e->getMessage(), 0, $e);
        }
        $tasks = \is_array($tasks) ? $tasks : [];

        // HTTP 200 with node or task failures lists only part of the cluster; a copy on an unreported node would
        // go unnoticed, so the list is no answer.
        if (!empty($tasks['node_failures']) || !empty($tasks['task_failures'])) {
            throw new ReindexFailedException('The task list is partial: ' . json_encode(
                ['node_failures' => $tasks['node_failures'] ?? [], 'task_failures' => $tasks['task_failures'] ?? []],
                JSON_PARTIAL_OUTPUT_ON_ERROR
            ));
        }

        return $tasks;
    }

    /**
     * @param array<string, mixed> $taskStatus
     */
    private function logProgress(string $taskId, array $taskStatus, int $poll): void
    {
        $status = $taskStatus['task']['status'] ?? [];
        $this->logger->info(sprintf(
            'Reindex task %s: %d of %d documents copied after %d seconds',
            $taskId,
            (int) ($status['created'] ?? 0) + (int) ($status['updated'] ?? 0),
            (int) ($status['total'] ?? 0),
            $poll * $this->reindexPollIntervalSeconds
        ));
    }

    /**
     * A previous aborted attempt may have left a reindex task behind that still
     * writes into the target name (see cancelTask()). It must be stopped before
     * the delete-first index creation — otherwise the old writer pollutes the
     * freshly created index with stale documents.
     *
     * @throws ReindexFailedException
     */
    private function ensureNoActiveReindexTask(string $targetIndexName): void
    {
        // Without the tasks API the async reindex could never be polled, cancelled
        // or verified — fail before an index is created or a task is submitted.
        if (!\is_callable([$this->client, 'getOriginalClient'])) {
            throw new ReindexFailedException(
                'The configured search client does not expose the tasks API'
                . ' (getOriginalClient()); the asynchronous reindex cannot be tracked.'
            );
        }

        try {
            $tasks = $this->listReindexTasks();
        } catch (ReindexFailedException $e) {
            throw new ReindexFailedException(
                sprintf(
                    'Could not verify that no previous reindex task is still writing into "%s": %s',
                    $targetIndexName,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }

        foreach ($tasks['nodes'] ?? [] as $node) {
            foreach ($node['tasks'] ?? [] as $taskId => $task) {
                $description = $task['description'] ?? '';
                if (!str_contains($description, '[' . $targetIndexName . ']')) {
                    continue;
                }

                $this->logger->warning(sprintf(
                    'Cancelling leftover reindex task %s from an earlier aborted attempt (%s)',
                    $taskId,
                    $description
                ));

                if (!$this->cancelTask((string) $taskId)) {
                    throw new ReindexFailedException(sprintf(
                        'A previous reindex task (%s) is still writing into "%s" and could not be stopped;'
                        . ' retry once it has finished.',
                        $taskId,
                        $targetIndexName
                    ));
                }
            }
        }
    }

    private function exceptionIndicatesMissingTask(\Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if (str_contains($current->getMessage(), 'resource_not_found')) {
                return true;
            }
        }

        return false;
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
            $this->doCreateIndex($indexName, $mappings);
        } catch (Exception $e) {
            $this->logger->error($e);
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    private function doCreateIndex(string $indexName, ?array $mappings): void
    {
        $this->logger->info("Creating index $indexName");

        $body = [];
        if (!empty($this->searchIndexConfigService->getIndexSettings())) {
            $body['settings'] = $this->searchIndexConfigService->getIndexSettings();
        }

        if ($mappings) {
            $normalized = $this->stripEmptyArraysFromMapping($mappings);
            if (!empty($normalized)) {
                $body['mappings']['properties'] = $normalized;
            }
        }

        $response = $this->client->createIndex(
            [
                'index' => $indexName,
                'body' => $body,
            ]
        );

        $this->logger->debug(json_encode($response, JSON_THROW_ON_ERROR));
    }

    private function stripEmptyArraysFromMapping(array $mapping): array
    {
        foreach ($mapping as $key => $value) {
            if (is_array($value)) {
                if (empty($value)) {
                    unset($mapping[$key]);
                } else {
                    $result = $this->stripEmptyArraysFromMapping($value);
                    if (empty($result)) {
                        unset($mapping[$key]);
                    } else {
                        $mapping[$key] = $result;
                    }
                }
            }
        }

        return $mapping;
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
        if (isset($params['body']['properties'])) {
            $normalized = $this->stripEmptyArraysFromMapping($params['body']['properties']);
            if (!empty($normalized)) {
                $params['body']['properties'] = $normalized;
            } else {
                unset($params['body']['properties']);
            }
        }

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
        $body = $this->searchBodyProcessorPipeline->process($search->toArray(), $indexName);

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
