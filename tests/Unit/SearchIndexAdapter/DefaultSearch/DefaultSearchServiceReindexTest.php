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
        $client = $this->buildClientWithTaskResponses(
            taskId: 'node:1',
            taskResponses: [
                ['completed' => true, 'response' => ['timed_out' => false, 'failures' => []]],
            ],
            withAliasSwitchSupport: true
        );

        // Must not throw
        $this->createService(client: $client)->reindex('test_index', []);
        $this->assertTrue(true); // reached without exception
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
        $responseObject = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['asArray'])
            ->getMock();
        $responseObject->method('asArray')->willReturn(
            ['completed' => true, 'response' => ['timed_out' => false, 'failures' => []]]
        );

        $tasksNamespace = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get'])
            ->getMock();
        $tasksNamespace->method('get')->willReturn($responseObject);

        $originalClient = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['tasks'])
            ->getMock();
        $originalClient->method('tasks')->willReturn($tasksNamespace);

        $client = $this->getMockBuilder(SearchClientInterface::class)
            ->addMethods(['getOriginalClient'])
            ->getMock();
        $client->method('existsIndex')->willReturn(false);
        $client->method('createIndex')->willReturn([]);
        $client->method('reIndex')->willReturn(['task' => 'node:99']);
        $client->method('getOriginalClient')->willReturn($originalClient);
        $client->method('updateIndexAliases')->willReturn(['acknowledged' => true]);
        $client->method('deleteIndex')->willReturn([]);

        $service = $this->createService(client: $client);
        $service->reindex('test_index', []);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Builds a client mock that sequences through $taskResponses on each tasks()->get() call,
     * and optionally supports alias switching for happy-path tests.
     */
    private function buildClientWithTaskResponses(
        string $taskId,
        array $taskResponses,
        bool $withAliasSwitchSupport = false,
        ?\Closure $onDeleteIndex = null
    ): SearchClientInterface {
        $callCount = 0;

        $tasksNamespace = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get'])
            ->getMock();
        $tasksNamespace->method('get')
            ->willReturnCallback(function (array $params) use ($taskResponses, &$callCount) {
                $response = $taskResponses[$callCount] ?? end($taskResponses);
                $callCount++;

                return $response;
            });

        $originalClient = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['tasks'])
            ->getMock();
        $originalClient->method('tasks')->willReturn($tasksNamespace);

        $client = $this->getMockBuilder(SearchClientInterface::class)
            ->addMethods(['getOriginalClient'])
            ->getMock();

        $client->method('existsIndex')->willReturn(true);
        $client->method('createIndex')->willReturn([]);
        $client->method('reIndex')->willReturn(['task' => $taskId]);
        $client->method('getOriginalClient')->willReturn($originalClient);
        $client->method('deleteIndex')->willReturnCallback(
            $onDeleteIndex ?? static fn(array $params): array => []
        );

        if ($withAliasSwitchSupport) {
            $client->method('updateIndexAliases')->willReturn(['acknowledged' => true]);
        }

        return $client;
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
