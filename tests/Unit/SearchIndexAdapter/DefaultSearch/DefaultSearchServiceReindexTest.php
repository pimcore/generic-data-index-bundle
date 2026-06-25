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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DefaultSearch;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\ReindexFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DefaultSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\SearchExecutionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\SearchClient\SearchClientInterface;
use Psr\Log\NullLogger;

/**
 * @internal
 */
final class DefaultSearchServiceReindexTest extends Unit
{
    // -------------------------------------------------------------------------
    // Submission: missing / empty task ID
    // -------------------------------------------------------------------------

    public function testReindexThrowsWhenResponseMissingTaskId(): void
    {
        $client = $this->makeEmpty(SearchClientInterface::class, [
            'existsIndex' => false,
            'createIndex' => [],
            'reIndex' => ['acknowledged' => true], // no 'task' key
        ]);

        $this->expectException(ReindexFailedException::class);
        $this->expectExceptionMessageMatches('/did not return a task ID/');

        $this->createService(client: $client)->reindex('test_index', []);
    }

    public function testReindexThrowsWhenTaskIdIsEmpty(): void
    {
        $client = $this->makeEmpty(SearchClientInterface::class, [
            'existsIndex' => false,
            'createIndex' => [],
            'reIndex' => ['task' => ''],
        ]);

        $this->expectException(ReindexFailedException::class);
        $this->expectExceptionMessageMatches('/did not return a task ID/');

        $this->createService(client: $client)->reindex('test_index', []);
    }

    // -------------------------------------------------------------------------
    // fetchTaskStatus: client without getOriginalClient()
    // -------------------------------------------------------------------------

    public function testReindexThrowsWhenClientLacksGetOriginalClient(): void
    {
        // Plain SearchClientInterface has no getOriginalClient() — duck-typing check fails
        $client = $this->makeEmpty(SearchClientInterface::class, [
            'existsIndex' => false,
            'createIndex' => [],
            'reIndex' => ['task' => 'node:42'],
            'deleteIndex' => [],
        ]);

        $this->expectException(ReindexFailedException::class);
        $this->expectExceptionMessageMatches('/getOriginalClient/');

        $this->createService(client: $client)->reindex('test_index', []);
    }

    // -------------------------------------------------------------------------
    // waitForTask: poll exhaustion
    // -------------------------------------------------------------------------

    public function testReindexThrowsWhenMaxPollsExceeded(): void
    {
        $client = $this->buildClientWithTaskResponses(
            taskId: 'node:1',
            taskResponses: [
                ['completed' => false],
                ['completed' => false],
                ['completed' => false],
            ]
        );

        $this->expectException(ReindexFailedException::class);
        $this->expectExceptionMessageMatches('/did not complete within/');

        // maxPolls=3, every response says not completed → exhausts on 3rd poll
        $this->createService(client: $client, reindexMaxPolls: 3)->reindex('test_index', []);
    }

    // -------------------------------------------------------------------------
    // waitForTask: task-level error
    // -------------------------------------------------------------------------

    public function testReindexThrowsOnTaskError(): void
    {
        $client = $this->buildClientWithTaskResponses(
            taskId: 'node:1',
            taskResponses: [
                ['completed' => true, 'error' => ['type' => 'node_lost', 'reason' => 'shard missing']],
            ]
        );

        $this->expectException(ReindexFailedException::class);
        $this->expectExceptionMessageMatches('/Reindex task failed/');

        $this->createService(client: $client)->reindex('test_index', []);
    }

    // -------------------------------------------------------------------------
    // waitForTask: server-side timeout inside response
    // -------------------------------------------------------------------------

    public function testReindexThrowsOnServerSideTimeout(): void
    {
        $client = $this->buildClientWithTaskResponses(
            taskId: 'node:1',
            taskResponses: [
                ['completed' => true, 'response' => ['timed_out' => true, 'failures' => []]],
            ]
        );

        $this->expectException(ReindexFailedException::class);
        $this->expectExceptionMessageMatches('/timed out server-side/');

        $this->createService(client: $client)->reindex('test_index', []);
    }

    // -------------------------------------------------------------------------
    // waitForTask: per-document failures inside response
    // -------------------------------------------------------------------------

    public function testReindexThrowsOnPerDocumentFailures(): void
    {
        $client = $this->buildClientWithTaskResponses(
            taskId: 'node:1',
            taskResponses: [
                [
                    'completed' => true,
                    'response' => [
                        'timed_out' => false,
                        'failures' => [['shard' => 0, 'reason' => 'disk full']],
                    ],
                ],
            ]
        );

        $this->expectException(ReindexFailedException::class);
        $this->expectExceptionMessageMatches('/completed with failures/');

        $this->createService(client: $client)->reindex('test_index', []);
    }

    // -------------------------------------------------------------------------
    // Happy path: clean completion on first poll
    // -------------------------------------------------------------------------

    public function testReindexSucceedsOnCleanCompletion(): void
    {
        $deletedIndices = [];

        $client = $this->buildClientWithTaskResponses(
            taskId: 'node:1',
            taskResponses: [
                ['completed' => true, 'response' => ['timed_out' => false, 'failures' => []]],
            ],
            withAliasSwitchSupport: true,
            onDeleteIndex: static function (array $params) use (&$deletedIndices): array {
                $deletedIndices[] = $params['index'];

                return [];
            }
        );

        // Must not throw
        $this->createService(client: $client)->reindex('test_index', []);

        // No alias exists → currentVersion='', newIndexName='test_index-even'.
        // createIndex() silently deletes test_index-even before recreating it.
        // switchIndexAliasAndCleanup() then deletes the orphaned test_index-odd suffix.
        $this->assertSame(['test_index-even', 'test_index-odd'], $deletedIndices);
    }

    // -------------------------------------------------------------------------
    // Happy path: multiple incomplete polls before success
    // -------------------------------------------------------------------------

    public function testReindexPollsUntilTaskCompletes(): void
    {
        $client = $this->buildClientWithTaskResponses(
            taskId: 'node:1',
            taskResponses: [
                ['completed' => false],
                ['completed' => false],
                ['completed' => true, 'response' => ['timed_out' => false, 'failures' => []]],
            ],
            withAliasSwitchSupport: true
        );

        $this->createService(client: $client, reindexMaxPolls: 5)->reindex('test_index', []);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Cleanup: new index deleted when polling fails
    // -------------------------------------------------------------------------

    public function testReindexDeletesNewIndexOnPollingFailure(): void
    {
        $deletedIndices = [];

        $client = $this->buildClientWithTaskResponses(
            taskId: 'node:1',
            taskResponses: [
                ['completed' => true, 'error' => ['type' => 'shard_failure']],
            ],
            onDeleteIndex: static function (array $params) use (&$deletedIndices): array {
                $deletedIndices[] = $params['index'];

                return [];
            }
        );

        try {
            $this->createService(client: $client)->reindex('test_index', []);
            $this->fail('Expected ReindexFailedException');
        } catch (ReindexFailedException) {
            // new index (test_index-even, because no existing alias → currentVersion='' → newVersion=even)
            $this->assertContains('test_index-even', $deletedIndices, 'New index must be cleaned up on failure');
        }
    }

    // -------------------------------------------------------------------------
    // fetchTaskStatus: response object with asArray() is normalised
    // -------------------------------------------------------------------------

    public function testReindexNormalisesObjectTaskResponseViaAsArray(): void
    {
        $client = new ReindexTestSearchClientStub(
            originalClient: new ReindexTestOriginalClientStub(
                new ReindexTestTasksStub([
                    new ReindexTestAsArrayResponseStub(
                        ['completed' => true, 'response' => ['timed_out' => false, 'failures' => []]]
                    ),
                ])
            ),
            taskId: 'node:99',
            withAliasSwitchSupport: true,
            existsIndexValue: false,
        );

        $this->createService(client: $client)->reindex('test_index', []);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Builds a client stub that sequences through $taskResponses on each tasks()->get() call.
     * Uses a concrete stub (not a PHPUnit mock) to avoid return-type enforcement on getOriginalClient().
     */
    private function buildClientWithTaskResponses(
        string $taskId,
        array $taskResponses,
        bool $withAliasSwitchSupport = false,
        ?\Closure $onDeleteIndex = null
    ): SearchClientInterface {
        return new ReindexTestSearchClientStub(
            originalClient: new ReindexTestOriginalClientStub(
                new ReindexTestTasksStub($taskResponses)
            ),
            taskId: $taskId,
            withAliasSwitchSupport: $withAliasSwitchSupport,
            onDeleteIndex: $onDeleteIndex,
        );
    }

    private function createService(
        ?SearchClientInterface $client = null,
        ?IndexAliasServiceInterface $indexAliasService = null,
        int $reindexMaxPolls = 3,
        int $reindexPollIntervalSeconds = 0,
    ): DefaultSearchService {
        $service = new DefaultSearchService(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $this->makeEmpty(SearchExecutionServiceInterface::class),
            $indexAliasService ?? $this->makeEmpty(IndexAliasServiceInterface::class, ['existsAlias' => false]),
            $client ?? $this->makeEmpty(SearchClientInterface::class),
            $reindexMaxPolls,
            $reindexPollIntervalSeconds,
        );
        $service->setLogger(new NullLogger());

        return $service;
    }
}

/**
 * Concrete stub implementing SearchClientInterface with an extra getOriginalClient() method.
 * Because getOriginalClient() is not declared in SearchClientInterface, PHPUnit's return-type
 * enforcement never applies — the method can return any object needed by the test.
 */
final class ReindexTestSearchClientStub implements SearchClientInterface
{
    public function __construct(
        private readonly object $originalClient,
        private readonly string $taskId,
        private readonly bool $withAliasSwitchSupport = false,
        private readonly ?\Closure $onDeleteIndex = null,
        private readonly bool $existsIndexValue = true,
    ) {
    }

    /** Duck-typed: not in SearchClientInterface, so no PHPUnit return-type check */
    public function getOriginalClient(): object
    {
        return $this->originalClient;
    }

    public function existsIndex(array $_params): bool
    {
        return $this->existsIndexValue;
    }

    public function reIndex(array $_params): array
    {
        return ['task' => $this->taskId];
    }

    public function deleteIndex(array $params): array
    {
        return $this->onDeleteIndex !== null ? ($this->onDeleteIndex)($params) : [];
    }

    public function updateIndexAliases(array $_params): array
    {
        return $this->withAliasSwitchSupport ? ['acknowledged' => true] : [];
    }

    public function create(array $_params): array
    {
        return [];
    }

    public function search(array $_params): array
    {
        return [];
    }

    public function get(array $_params): array
    {
        return [];
    }

    public function exists(array $_params): bool
    {
        return false;
    }

    public function count(array $_params): array
    {
        return [];
    }

    public function index(array $_params): array
    {
        return [];
    }

    public function bulk(array $_params): array
    {
        return [];
    }

    public function delete(array $_params): array
    {
        return [];
    }

    public function updateByQuery(array $_params): array
    {
        return [];
    }

    public function deleteByQuery(array $_params): array
    {
        return [];
    }

    public function createIndex(array $_params): array
    {
        return [];
    }

    public function openIndex(array $_params): array
    {
        return [];
    }

    public function closeIndex(array $_params): array
    {
        return [];
    }

    public function getAllIndices(array $_params): array
    {
        return [];
    }

    public function refreshIndex(array $_params = []): array
    {
        return [];
    }

    public function flushIndex(array $_params = []): array
    {
        return [];
    }

    public function existsIndexAlias(array $_params): bool
    {
        return false;
    }

    public function getIndexAlias(array $_params): array
    {
        return [];
    }

    public function deleteIndexAlias(array $_params): array
    {
        return [];
    }

    public function getAllIndexAliases(array $_params): array
    {
        return [];
    }

    public function putIndexMapping(array $_params): array
    {
        return [];
    }

    public function getIndexMapping(array $_params): array
    {
        return [];
    }

    public function getIndexSettings(array $_params): array
    {
        return [];
    }

    public function putIndexSettings(array $_params): array
    {
        return [];
    }

    public function getIndexStats(array $_params): array
    {
        return [];
    }
}

/** Stubs for the task polling chain: getOriginalClient()->tasks()->get() */
final class ReindexTestTasksStub
{
    private int $callCount = 0;

    public function __construct(private readonly array $responses)
    {
    }

    public function get(array $params): mixed
    {
        $response = $this->responses[$this->callCount] ?? end($this->responses);
        $this->callCount++;

        return $response;
    }
}

final class ReindexTestOriginalClientStub
{
    public function __construct(private readonly ReindexTestTasksStub $tasksStub)
    {
    }

    public function tasks(): ReindexTestTasksStub
    {
        return $this->tasksStub;
    }
}

final class ReindexTestAsArrayResponseStub
{
    public function __construct(private readonly array $data)
    {
    }

    public function asArray(): array
    {
        return $this->data;
    }
}
