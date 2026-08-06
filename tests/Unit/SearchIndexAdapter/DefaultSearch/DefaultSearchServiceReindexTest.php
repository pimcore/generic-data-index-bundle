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
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ReindexResult;
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
    // Per-document failures: expected structural outcome, reported as result
    // -------------------------------------------------------------------------

    /**
     * Documents that cannot be indexed into the new mapping (e.g. after a field
     * type change) are an expected outcome, not an error: reported as
     * MAPPING_INCOMPATIBLE so the caller can decide to recreate the index. The
     * unusable new index is cleaned up and the alias is left untouched.
     */
    public function testReindexReturnsMappingIncompatibleOnStructuralDocumentFailures(): void
    {
        $deletedIndices = [];

        $client = $this->buildClientWithTaskResponses(
            taskId: 'node:1',
            taskResponses: [
                [
                    'completed' => true,
                    'response' => [
                        'timed_out' => false,
                        'failures' => [
                            [
                                'index' => 'test_index-even',
                                'id' => '42',
                                'cause' => ['type' => 'mapper_parsing_exception', 'reason' => 'failed to parse field'],
                                'status' => 400,
                            ],
                            [
                                'index' => 'test_index-even',
                                'id' => '43',
                                'cause' => ['type' => 'strict_dynamic_mapping_exception', 'reason' => 'not allowed'],
                                'status' => 400,
                            ],
                        ],
                    ],
                ],
            ],
            onDeleteIndex: static function (array $params) use (&$deletedIndices): array {
                $deletedIndices[] = $params['index'];

                return [];
            }
        );

        $result = $this->createService(client: $client)->reindex('test_index', []);

        $this->assertSame(ReindexResult::MAPPING_INCOMPATIBLE, $result);
        $this->assertContains('test_index-even', $deletedIndices, 'The unusable new index must be cleaned up');
    }

    /**
     * The bulk-failure list can also contain transient causes (rejected executions,
     * disk watermark blocks, unavailable shards). Those must never be reported as
     * MAPPING_INCOMPATIBLE — the caller would recreate the live index over a
     * temporary cluster condition.
     */
    public function testReindexThrowsOnTransientDocumentFailures(): void
    {
        $client = $this->buildClientWithTaskResponses(
            taskId: 'node:1',
            taskResponses: [
                [
                    'completed' => true,
                    'response' => [
                        'timed_out' => false,
                        'failures' => [
                            [
                                'index' => 'test_index-even',
                                'id' => '42',
                                'cause' => ['type' => 'es_rejected_execution_exception', 'reason' => 'queue full'],
                                'status' => 429,
                            ],
                        ],
                    ],
                ],
            ]
        );

        $this->expectException(ReindexFailedException::class);
        $this->expectExceptionMessageMatches('/completed with failures/');

        $this->createService(client: $client)->reindex('test_index', []);
    }

    public function testReindexThrowsOnMixedDocumentFailures(): void
    {
        $client = $this->buildClientWithTaskResponses(
            taskId: 'node:1',
            taskResponses: [
                [
                    'completed' => true,
                    'response' => [
                        'timed_out' => false,
                        'failures' => [
                            [
                                'cause' => ['type' => 'mapper_parsing_exception', 'reason' => 'failed to parse'],
                            ],
                            [
                                'cause' => ['type' => 'cluster_block_exception', 'reason' => 'disk watermark exceeded'],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $this->expectException(ReindexFailedException::class);

        $this->createService(client: $client)->reindex('test_index', []);
    }

    // -------------------------------------------------------------------------
    // Poll resilience: transient task-status failures are retried
    // -------------------------------------------------------------------------

    /**
     * A single failed status request must not abort a long-running reindex —
     * transient hiccups are expected while _reindex puts load on the cluster.
     *
     * @see https://github.com/pimcore/service-operations/issues/1126
     */
    public function testReindexRetriesTransientPollFailures(): void
    {
        $client = $this->buildClientWithTaskResponses(
            taskId: 'node:1',
            taskResponses: [
                new Exception('cURL error 7: connection refused'),
                ['completed' => false],
                new Exception('cURL error 28: request timed out'),
                ['completed' => true, 'response' => ['timed_out' => false, 'failures' => []]],
            ],
            withAliasSwitchSupport: true
        );

        $result = $this->createService(client: $client, reindexMaxPolls: 10)->reindex('test_index', []);

        $this->assertSame(ReindexResult::SUCCESS, $result);
    }

    public function testReindexThrowsAfterPersistentPollFailures(): void
    {
        $deletedIndices = [];

        $client = $this->buildClientWithTaskResponses(
            taskId: 'node:1',
            taskResponses: [
                new Exception('cURL error 7: connection refused'),
            ],
            onDeleteIndex: static function (array $params) use (&$deletedIndices): array {
                $deletedIndices[] = $params['index'];

                return [];
            }
        );

        try {
            $this->createService(client: $client, reindexMaxPolls: 10)->reindex('test_index', []);
            $this->fail('Expected ReindexFailedException');
        } catch (ReindexFailedException) {
            // the cluster is unreachable, so the cancellation cannot be confirmed
            // either — the target index is kept for the next attempt's cleanup
            $this->assertSame(
                1,
                $this->countDeletes($deletedIndices, 'test_index-even'),
                'The target index must be kept while the task state is unknown'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Cleanup: the server-side task is cancelled before the new index is deleted
    // -------------------------------------------------------------------------

    /**
     * The kickoff request can be accepted server-side while the client fails to
     * receive the response — a task may be running whose ID was never learned.
     * Without a task ID the target must be kept, not deleted under an unknown writer.
     */
    public function testReindexKeepsNewIndexWhenTaskIdIsUnknown(): void
    {
        $deletedIndices = [];

        $client = $this->makeEmpty(SearchClientInterface::class, [
            'existsIndex' => true,
            'createIndex' => [],
            'reIndex' => ['acknowledged' => true], // no 'task' key — outcome unknown
            'deleteIndex' => function (array $params) use (&$deletedIndices): array {
                $deletedIndices[] = $params['index'];

                return [];
            },
        ]);

        try {
            $this->createService(client: $client)->reindex('test_index', []);
            $this->fail('Expected ReindexFailedException');
        } catch (ReindexFailedException) {
            $this->assertSame(
                1,
                $this->countDeletes($deletedIndices, 'test_index-even'),
                'Without a known task ID the target index must be kept — a task may still write into it'
            );
        }
    }

    /**
     * Cancellation is cooperative: the cancel API only flags the task, which stops
     * between batches. As long as the task is still observable as running, the
     * target must be kept.
     */
    public function testReindexKeepsNewIndexWhenCancelledTaskIsStillRunning(): void
    {
        $deletedIndices = [];
        $tasksStub = new ReindexTestTasksStub([
            new Exception('connection refused'),
            new Exception('connection refused'),
            new Exception('connection refused'),
            ['completed' => false], // post-cancel verification: still running
        ]);

        $client = new ReindexTestSearchClientStub(
            originalClient: new ReindexTestOriginalClientStub($tasksStub),
            taskId: 'node:42',
            onDeleteIndex: static function (array $params) use (&$deletedIndices): array {
                $deletedIndices[] = $params['index'];

                return [];
            },
        );

        try {
            $this->createService(client: $client, reindexMaxPolls: 10)->reindex('test_index', []);
            $this->fail('Expected ReindexFailedException');
        } catch (ReindexFailedException) {
            $this->assertSame(
                1,
                $this->countDeletes($deletedIndices, 'test_index-even'),
                'The target index must be kept while the cancelled task is still observable as running'
            );
        }
    }

    public function testReindexCancelsServerSideTaskWhenAborting(): void
    {
        $deletedIndices = [];
        $tasksStub = new ReindexTestTasksStub([
            ['completed' => false],
            new Exception('connection lost'),
            new Exception('connection lost'),
            new Exception('connection lost'),
            ['completed' => true], // post-cancel verification: task stopped
        ]);

        $client = new ReindexTestSearchClientStub(
            originalClient: new ReindexTestOriginalClientStub($tasksStub),
            taskId: 'node:42',
            onDeleteIndex: static function (array $params) use (&$deletedIndices): array {
                $deletedIndices[] = $params['index'];

                return [];
            },
        );

        try {
            $this->createService(client: $client, reindexMaxPolls: 10)->reindex('test_index', []);
            $this->fail('Expected ReindexFailedException');
        } catch (ReindexFailedException) {
            $this->assertSame(
                ['node:42'],
                $tasksStub->cancelledTasks,
                'The still-running server-side task must be cancelled before its target index is deleted'
            );
            // one delete from createIndex() (delete-first), one from the abort cleanup
            $this->assertSame(
                2,
                $this->countDeletes($deletedIndices, 'test_index-even'),
                'The target index is safe to delete after confirmed cancellation'
            );
        }
    }

    /**
     * If the cancellation cannot be confirmed (e.g. the same outage that aborted the
     * polling), the target index must be kept — the still-running server-side task
     * would otherwise auto-recreate the dropped index and keep writing into it. The
     * leftover index is removed by the next reindex attempt.
     */
    public function testReindexKeepsNewIndexWhenTaskCancellationIsUnconfirmed(): void
    {
        $deletedIndices = [];
        $tasksStub = new ReindexTestTasksStub([
            new Exception('connection refused'),
        ]);
        $tasksStub->cancelException = new Exception('connection refused');

        $client = new ReindexTestSearchClientStub(
            originalClient: new ReindexTestOriginalClientStub($tasksStub),
            taskId: 'node:42',
            onDeleteIndex: static function (array $params) use (&$deletedIndices): array {
                $deletedIndices[] = $params['index'];

                return [];
            },
        );

        try {
            $this->createService(client: $client, reindexMaxPolls: 10)->reindex('test_index', []);
            $this->fail('Expected ReindexFailedException');
        } catch (ReindexFailedException) {
            // only the delete-first inside createIndex(); NO abort cleanup delete
            $this->assertSame(
                1,
                $this->countDeletes($deletedIndices, 'test_index-even'),
                'The target index must be kept while the server-side task may still write into it'
            );
        }
    }

    public function testReindexTreatsAlreadyFinishedTaskAsCancelled(): void
    {
        $deletedIndices = [];
        $tasksStub = new ReindexTestTasksStub([
            new Exception('connection refused'),
        ]);
        $tasksStub->cancelException = new Exception(
            '{"error":{"type":"resource_not_found_exception","reason":"task [node:42] is not found"}}'
        );

        $client = new ReindexTestSearchClientStub(
            originalClient: new ReindexTestOriginalClientStub($tasksStub),
            taskId: 'node:42',
            onDeleteIndex: static function (array $params) use (&$deletedIndices): array {
                $deletedIndices[] = $params['index'];

                return [];
            },
        );

        try {
            $this->createService(client: $client, reindexMaxPolls: 10)->reindex('test_index', []);
            $this->fail('Expected ReindexFailedException');
        } catch (ReindexFailedException) {
            $this->assertSame(
                2,
                $this->countDeletes($deletedIndices, 'test_index-even'),
                'A task that no longer exists cannot write anymore — the target index is safe to delete'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Pre-flight: leftover task from an earlier aborted attempt
    // -------------------------------------------------------------------------

    /**
     * When a previous attempt aborted without confirmed cancellation, its task may
     * still write into the target name. Before the delete-first index creation, a
     * still-running writer must be stopped — otherwise it pollutes the new index.
     */
    public function testReindexCancelsLeftoverTaskWritingIntoTarget(): void
    {
        $tasksStub = new ReindexTestTasksStub([
            ['completed' => true], // verification of the cancelled leftover task
            ['completed' => true, 'response' => ['timed_out' => false, 'failures' => []]],
        ]);
        $tasksStub->listResponse = [
            'nodes' => [
                'node1' => [
                    'tasks' => [
                        'node1:99' => [
                            'action' => 'indices:data/write/reindex',
                            'description' => 'reindex from [test_index-odd] to [test_index-even]',
                        ],
                    ],
                ],
            ],
        ];

        $client = new ReindexTestSearchClientStub(
            originalClient: new ReindexTestOriginalClientStub($tasksStub),
            taskId: 'node:1',
            withAliasSwitchSupport: true,
        );

        $result = $this->createService(client: $client)->reindex('test_index', []);

        $this->assertSame(ReindexResult::SUCCESS, $result);
        $this->assertContains(
            'node1:99',
            $tasksStub->cancelledTasks,
            'A leftover task still writing into the target must be cancelled before the index is recreated'
        );
    }

    public function testReindexThrowsWhenLeftoverTaskCannotBeStopped(): void
    {
        $tasksStub = new ReindexTestTasksStub([]);
        $tasksStub->listResponse = [
            'nodes' => [
                'node1' => [
                    'tasks' => [
                        'node1:99' => [
                            'action' => 'indices:data/write/reindex',
                            'description' => 'reindex from [test_index-odd] to [test_index-even]',
                        ],
                    ],
                ],
            ],
        ];
        $tasksStub->cancelException = new Exception('connection refused');

        $client = new ReindexTestSearchClientStub(
            originalClient: new ReindexTestOriginalClientStub($tasksStub),
            taskId: 'node:1',
        );

        $this->expectException(ReindexFailedException::class);
        $this->expectExceptionMessageMatches('/still writing/');

        $this->createService(client: $client)->reindex('test_index', []);
    }

    /**
     * @param array<int, string> $deletedIndices
     */
    private function countDeletes(array $deletedIndices, string $indexName): int
    {
        return count(array_filter($deletedIndices, static fn (string $name): bool => $name === $indexName));
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

        $result = $this->createService(client: $client)->reindex('test_index', []);

        $this->assertSame(ReindexResult::SUCCESS, $result);

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

        $result = $this->createService(client: $client, reindexMaxPolls: 5)->reindex('test_index', []);
        $this->assertSame(ReindexResult::SUCCESS, $result);
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

/**
 * Stubs for the task polling chain: getOriginalClient()->tasks()->get().
 * A Throwable entry in $responses simulates a failed status request.
 */
final class ReindexTestTasksStub
{
    private int $callCount = 0;

    /** @var array<int, mixed> */
    public array $cancelledTasks = [];

    public ?\Throwable $cancelException = null;

    /** @var array<string, mixed> */
    public array $listResponse = [];

    public function __construct(private readonly array $responses)
    {
    }

    public function list(array $params = []): array
    {
        return $this->listResponse;
    }

    public function get(array $params): mixed
    {
        $response = $this->responses[$this->callCount] ?? end($this->responses);
        $this->callCount++;

        if ($response instanceof \Throwable) {
            throw $response;
        }

        return $response;
    }

    public function cancel(array $params): array
    {
        $this->cancelledTasks[] = $params['task_id'] ?? null;

        if ($this->cancelException !== null) {
            throw $this->cancelException;
        }

        return [];
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
