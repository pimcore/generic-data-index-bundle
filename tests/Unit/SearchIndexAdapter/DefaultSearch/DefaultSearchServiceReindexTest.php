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

use Codeception\Stub;
use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\ReindexFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DefaultSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\SearchExecutionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\SearchClient\SearchClientInterface;

/**
 * @internal
 */
final class DefaultSearchServiceReindexTest extends Unit
{
    public function testReindexKicksOffAsyncRequestAndSwapsAliasOnSuccess(): void
    {
        $reindexParams = null;
        $deletedIndexes = [];

        $client = $this->makeEmpty(SearchClientInterface::class, [
            'existsIndex' => true,
            'existsIndexAlias' => true,
            'getIndexAlias' => ['pimcore_test-index-odd' => []],
            'reIndex' => function (array $params) use (&$reindexParams) {
                $reindexParams = $params;

                return ['task' => 'node:42'];
            },
            'updateIndexAliases' => ['acknowledged' => true],
            'deleteIndex' => function (array $params) use (&$deletedIndexes) {
                $deletedIndexes[] = $params['index'];

                return [];
            },
            'createIndex' => ['acknowledged' => true],
        ]);

        $service = $this->makeTestableService(
            $client,
            tasks: ['node:42' => ['completed' => true, 'response' => ['took' => 100, 'total' => 5, 'failures' => []]]],
        );

        $service->reindex('pimcore_test-index', ['some' => ['type' => 'keyword']]);

        self::assertNotNull($reindexParams);
        self::assertFalse(
            $reindexParams['wait_for_completion'],
            'wait_for_completion must be false so the request returns immediately with a task id'
        );
        self::assertSame('pimcore_test-index-odd', $reindexParams['body']['source']['index']);
        self::assertSame('pimcore_test-index-even', $reindexParams['body']['dest']['index']);

        // Old index should be cleaned up by switchIndexAliasAndCleanup;
        // new (target) index must NOT be deleted on success.
        self::assertContains('pimcore_test-index-odd', $deletedIndexes);
        self::assertNotContains('pimcore_test-index-even', $deletedIndexes);
    }

    public function testReindexCleansUpOrphanWhenTaskCompletesWithError(): void
    {
        $deletedIndexes = [];

        $client = $this->makeEmpty(SearchClientInterface::class, [
            'existsIndex' => true,
            'existsIndexAlias' => true,
            'getIndexAlias' => ['pimcore_test-index-odd' => []],
            'reIndex' => ['task' => 'node:99'],
            'updateIndexAliases' => Expected::never(),
            'deleteIndex' => function (array $params) use (&$deletedIndexes) {
                $deletedIndexes[] = $params['index'];

                return [];
            },
            'createIndex' => ['acknowledged' => true],
        ]);

        $service = $this->makeTestableService(
            $client,
            tasks: ['node:99' => ['completed' => true, 'error' => ['type' => 'index_not_found_exception']]],
        );

        try {
            $service->reindex('pimcore_test-index', ['some' => ['type' => 'keyword']]);
            self::fail('Expected ReindexFailedException');
        } catch (ReindexFailedException $e) {
            self::assertStringContainsString('node:99', $e->getMessage());
            self::assertStringContainsString('index_not_found_exception', $e->getMessage());
        }

        self::assertContains(
            'pimcore_test-index-even',
            $deletedIndexes,
            'Orphan target index must be deleted when reindex fails'
        );
    }

    public function testReindexCleansUpOrphanWhenTaskCompletesWithDocumentFailures(): void
    {
        $deletedIndexes = [];

        $client = $this->makeEmpty(SearchClientInterface::class, [
            'existsIndex' => true,
            'existsIndexAlias' => true,
            'getIndexAlias' => ['pimcore_test-index-odd' => []],
            'reIndex' => ['task' => 'node:7'],
            'updateIndexAliases' => Expected::never(),
            'deleteIndex' => function (array $params) use (&$deletedIndexes) {
                $deletedIndexes[] = $params['index'];

                return [];
            },
            'createIndex' => ['acknowledged' => true],
        ]);

        $service = $this->makeTestableService(
            $client,
            tasks: ['node:7' => [
                'completed' => true,
                'response' => ['failures' => [['type' => 'mapper_parsing_exception']]],
            ]],
        );

        try {
            $service->reindex('pimcore_test-index', ['some' => ['type' => 'keyword']]);
            self::fail('Expected ReindexFailedException');
        } catch (ReindexFailedException $e) {
            self::assertStringContainsString('document failure', $e->getMessage());
        }

        self::assertContains('pimcore_test-index-even', $deletedIndexes);
    }

    public function testReindexCleansUpOrphanWhenReIndexItselfThrows(): void
    {
        $original = new Exception('Failed to reindex: 504 Gateway Time-out');
        $deletedIndexes = [];

        $client = $this->makeEmpty(SearchClientInterface::class, [
            'existsIndex' => true,
            'existsIndexAlias' => true,
            'getIndexAlias' => ['pimcore_test-index-odd' => []],
            'reIndex' => function () use ($original): void {
                throw $original;
            },
            'updateIndexAliases' => Expected::never(),
            'deleteIndex' => function (array $params) use (&$deletedIndexes) {
                $deletedIndexes[] = $params['index'];

                return [];
            },
            'createIndex' => ['acknowledged' => true],
        ]);

        $service = $this->makeTestableService($client);

        try {
            $service->reindex('pimcore_test-index', ['some' => ['type' => 'keyword']]);
            self::fail('Expected ReindexFailedException');
        } catch (ReindexFailedException $e) {
            self::assertSame(
                $original,
                $e->getPrevious(),
                'Original exception must be preserved as previous, not lost via shadowing'
            );
            self::assertStringContainsString('504', $e->getMessage());
        }

        self::assertContains('pimcore_test-index-even', $deletedIndexes);
    }

    public function testReindexThrowsAndCleansUpWhenResponseIsMissingTaskId(): void
    {
        $deletedIndexes = [];

        $client = $this->makeEmpty(SearchClientInterface::class, [
            'existsIndex' => true,
            'existsIndexAlias' => true,
            'getIndexAlias' => ['pimcore_test-index-odd' => []],
            'reIndex' => [],  // no 'task' key
            'updateIndexAliases' => Expected::never(),
            'deleteIndex' => function (array $params) use (&$deletedIndexes) {
                $deletedIndexes[] = $params['index'];

                return [];
            },
            'createIndex' => ['acknowledged' => true],
        ]);

        $service = $this->makeTestableService($client);

        try {
            $service->reindex('pimcore_test-index', ['some' => ['type' => 'keyword']]);
            self::fail('Expected ReindexFailedException');
        } catch (ReindexFailedException $e) {
            self::assertStringContainsString('task id', $e->getMessage());
        }

        self::assertContains('pimcore_test-index-even', $deletedIndexes);
    }

    public function testReindexTimesOutAndCleansUpOrphanWhenTaskNeverCompletes(): void
    {
        $deletedIndexes = [];

        $client = $this->makeEmpty(SearchClientInterface::class, [
            'existsIndex' => true,
            'existsIndexAlias' => true,
            'getIndexAlias' => ['pimcore_test-index-odd' => []],
            'reIndex' => ['task' => 'node:slow'],
            'updateIndexAliases' => Expected::never(),
            'deleteIndex' => function (array $params) use (&$deletedIndexes) {
                $deletedIndexes[] = $params['index'];

                return [];
            },
            'createIndex' => ['acknowledged' => true],
        ]);

        $config = $this->makeEmpty(SearchIndexConfigServiceInterface::class, [
            'getReindexPollIntervalSeconds' => 1,
            'getReindexMaxWaitSeconds' => 1, // tight deadline
            'getIndexSettings' => [],
        ]);

        $service = $this->makeTestableService(
            $client,
            tasks: ['node:slow' => ['completed' => false]],
            config: $config,
        );

        try {
            $service->reindex('pimcore_test-index', ['some' => ['type' => 'keyword']]);
            self::fail('Expected ReindexFailedException');
        } catch (ReindexFailedException $e) {
            self::assertStringContainsString('did not complete', $e->getMessage());
        }

        self::assertContains('pimcore_test-index-even', $deletedIndexes);
    }

    /**
     * @param array<string, array<string, mixed>> $tasks  taskId => task info to return
     */
    private function makeTestableService(
        SearchClientInterface $client,
        array $tasks = [],
        ?SearchIndexConfigServiceInterface $config = null,
    ): DefaultSearchService {
        $config ??= $this->makeEmpty(SearchIndexConfigServiceInterface::class, [
            'getReindexPollIntervalSeconds' => 1,
            'getReindexMaxWaitSeconds' => 60,
            'getIndexSettings' => [],
        ]);

        return new class(
            $config,
            $this->makeEmpty(SearchExecutionServiceInterface::class),
            $this->makeEmpty(IndexAliasServiceInterface::class, [
                'existsAlias' => true,
                'addAlias' => [],
            ]),
            $client,
            $tasks,
        ) extends DefaultSearchService {
            /**
             * @param array<string, array<string, mixed>> $tasks
             */
            public function __construct(
                SearchIndexConfigServiceInterface $searchIndexConfigService,
                SearchExecutionServiceInterface $searchExecutionService,
                IndexAliasServiceInterface $indexAliasService,
                SearchClientInterface $client,
                private readonly array $tasks,
            ) {
                parent::__construct(
                    $searchIndexConfigService,
                    $searchExecutionService,
                    $indexAliasService,
                    $client,
                );
            }

            protected function getTaskInfo(string $taskId): array
            {
                return $this->tasks[$taskId] ?? ['completed' => false];
            }
        };
    }
}
