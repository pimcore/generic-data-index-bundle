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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\SearchIndexAdapter\DefaultSearch\Search;

use Codeception\Test\Unit;
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\FieldExpansionFallback;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\SearchRequestExecutor;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Stringable;

/**
 * @internal
 */
final class SearchRequestExecutorTest extends Unit
{
    private const CONFIGURED_FIELDS = ['system_fields.key.analyzed', 'standard_fields.sku.keyword'];

    public function testConfiguredFieldsAreUsedForTheFirstRequest(): void
    {
        $bodies = [];
        $request = function (array $params) use (&$bodies) {
            $bodies[] = $params['body'];

            return ['hits' => ['hits' => [1]]];
        };

        $this->createExecutor(self::CONFIGURED_FIELDS)->execute(
            $request,
            ['index' => 'i', 'body' => ['query' => ['bool' => ['filter' => [
                ['simple_query_string' => ['query' => 'pro']],
            ]]]]]
        );

        $this->assertCount(1, $bodies);
        $this->assertSame(
            self::CONFIGURED_FIELDS,
            $bodies[0]['query']['bool']['filter'][0]['simple_query_string']['fields']
        );
    }

    public function testExplicitFieldsOfAQueryAreKeptWhenFieldsAreConfigured(): void
    {
        $bodies = [];
        $request = function (array $params) use (&$bodies) {
            $bodies[] = $params['body'];

            return ['count' => 1];
        };

        $this->createExecutor(self::CONFIGURED_FIELDS)->execute(
            $request,
            ['index' => 'i', 'body' => ['query' => ['simple_query_string' => ['query' => 'pro', 'fields' => ['a']]]]]
        );

        $this->assertSame(['a'], $bodies[0]['query']['simple_query_string']['fields']);
    }

    public function testRequestIsUnchangedWithoutConfiguredFields(): void
    {
        $bodies = [];
        $request = function (array $params) use (&$bodies) {
            $bodies[] = $params['body'];

            return ['count' => 1];
        };

        $this->createExecutor([])->execute(
            $request,
            ['index' => 'i', 'body' => ['query' => ['simple_query_string' => ['query' => 'pro']]]]
        );

        $this->assertCount(1, $bodies);
        $this->assertArrayNotHasKey('fields', $bodies[0]['query']['simple_query_string']);
    }

    public function testRequestWithoutBodyIsUnchangedWhenFieldsAreConfigured(): void
    {
        $sent = [];
        $request = function (array $params) use (&$sent) {
            $sent[] = $params;

            return ['count' => 1];
        };

        $this->createExecutor(self::CONFIGURED_FIELDS)->execute($request, ['index' => 'i']);

        $this->assertSame([['index' => 'i']], $sent);
    }

    public function testFieldExpansionFallbackStillAppliesWithoutConfiguredFields(): void
    {
        $bodies = [];
        $request = function (array $params) use (&$bodies) {
            $bodies[] = $params['body'];
            if (count($bodies) === 1) {
                throw new Exception('field expansion matches too many fields, limit: 1024, got: 2955');
            }

            return ['count' => 1];
        };

        $this->createExecutor([])->execute(
            $request,
            ['index' => 'i', 'body' => ['query' => ['simple_query_string' => ['query' => 'pro']]]]
        );

        $this->assertCount(2, $bodies);
        $this->assertSame(FieldExpansionFallback::FIELDS, $bodies[1]['query']['simple_query_string']['fields']);
    }

    public function testFailedShardsAreLoggedWithTheirReasons(): void
    {
        $logger = $this->createRecordingLogger();
        $response = [
            '_shards' => [
                'total' => 3,
                'successful' => 1,
                'failed' => 2,
                'failures' => [
                    ['index' => 'pimcore_product', 'reason' => ['type' => 'query_shard_exception', 'reason' => 'boom']],
                    ['index' => 'pimcore_product', 'reason' => ['type' => 'query_shard_exception', 'reason' => 'boom']],
                ],
            ],
            'hits' => ['hits' => []],
        ];

        $result = $this->createExecutor([], $logger)->execute(
            fn () => $response,
            ['index' => 'pimcore_data-object', 'body' => ['query' => ['match_all' => (object) []]]]
        );

        $this->assertSame($response, $result);
        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
        $this->assertSame('pimcore_data-object', $logger->records[0]['context']['index']);
        $this->assertSame(2, $logger->records[0]['context']['failedShards']);
        $this->assertSame(3, $logger->records[0]['context']['totalShards']);
        $this->assertSame(
            ['pimcore_product: query_shard_exception: boom'],
            $logger->records[0]['context']['reasons']
        );
    }

    public function testSuccessfulResponsesAreNotLogged(): void
    {
        $logger = $this->createRecordingLogger();

        $this->createExecutor([], $logger)->execute(
            fn () => ['_shards' => ['total' => 1, 'successful' => 1, 'failed' => 0], 'count' => 1],
            ['index' => 'i', 'body' => []]
        );

        $this->assertSame([], $logger->records);
    }

    /**
     * @param string[] $fullTextSearchFields
     */
    private function createExecutor(
        array $fullTextSearchFields,
        ?AbstractLogger $logger = null
    ): SearchRequestExecutor {
        return new SearchRequestExecutor(
            $this->makeEmpty(SearchIndexConfigServiceInterface::class, [
                'getSearchSettings' => ['full_text_search_fields' => $fullTextSearchFields],
            ]),
            $logger ?? new NullLogger(),
        );
    }

    private function createRecordingLogger(): AbstractLogger
    {
        return new class() extends AbstractLogger {
            /** @var array<int, array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
    }
}
