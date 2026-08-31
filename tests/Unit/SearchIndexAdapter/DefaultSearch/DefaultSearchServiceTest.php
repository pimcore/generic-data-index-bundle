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

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DefaultSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Processor\SearchBodyProcessorInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Processor\SearchBodyProcessorPipeline;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\SearchExecutionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\SearchClient\SearchClientInterface;
use stdClass;

/**
 * @internal
 */
final class DefaultSearchServiceTest extends Unit
{
    public function testGetCountReturnsCountFromResult(): void
    {
        $service = $this->createService(
            client: $this->makeEmpty(SearchClientInterface::class, [
                'count' => Expected::once(['count' => 42]),
            ])
        );

        $search = $this->makeEmpty(AdapterSearchInterface::class, [
            'toArray' => [],
        ]);

        $this->assertSame(42, $service->getCount($search, 'test_index'));
    }

    public function testGetCountReturnsZeroWhenCountKeyIsMissing(): void
    {
        $service = $this->createService(
            client: $this->makeEmpty(SearchClientInterface::class, [
                'count' => Expected::once([]),
            ])
        );

        $search = $this->makeEmpty(AdapterSearchInterface::class, [
            'toArray' => [],
        ]);

        $this->assertSame(0, $service->getCount($search, 'test_index'));
    }

    public function testGetCountReturnsZeroWhenCountIsZero(): void
    {
        $service = $this->createService(
            client: $this->makeEmpty(SearchClientInterface::class, [
                'count' => ['count' => 0],
            ])
        );

        $search = $this->makeEmpty(AdapterSearchInterface::class, [
            'toArray' => [],
        ]);

        $this->assertSame(0, $service->getCount($search, 'test_index'));
    }

    public function testGetCountStripsDisallowedKeysFromBody(): void
    {
        $service = $this->createService(
            client: $this->makeEmpty(SearchClientInterface::class, [
                'count' => Expected::once(function (array $params) {
                    $body = $params['body'];
                    $this->assertArrayNotHasKey('_source', $body);
                    $this->assertArrayNotHasKey('sort', $body);
                    $this->assertArrayNotHasKey('from', $body);
                    $this->assertArrayNotHasKey('size', $body);
                    $this->assertArrayNotHasKey('aggs', $body);
                    $this->assertArrayHasKey('query', $body);

                    return ['count' => 10];
                }),
            ])
        );

        $search = $this->makeEmpty(AdapterSearchInterface::class, [
            'toArray' => [
                'query' => ['match_all' => new \stdClass()],
                '_source' => ['field1'],
                'sort' => ['field1' => 'asc'],
                'from' => 0,
                'size' => 10,
                'aggs' => ['my_agg' => []],
            ],
        ]);

        $this->assertSame(10, $service->getCount($search, 'test_index'));
    }

    /**
     * When createIndex() receives a mappings array whose only field has an empty-array value,
     * normalization removes all fields, leaving an empty properties array. The entire
     * "mappings" key must then be omitted from the body so OpenSearch never receives
     * "mappings":{"properties":{}} or "mappings":{"properties":[]}.
     */
    public function testCreateIndexRemovesTopLevelEmptyMappingArrayFields(): void
    {
        $capturedBody = null;

        $service = $this->createService(
            client: $this->makeEmpty(SearchClientInterface::class, [
                'existsIndex' => false,
                'createIndex' => Expected::once(function (array $params) use (&$capturedBody): array {
                    $capturedBody = $params['body'];

                    return [];
                }),
            ])
        );

        $service->createIndex('test_index', ['my_field' => []]);

        $this->assertNotNull($capturedBody, 'createIndex must have been called on the client');
        $this->assertArrayNotHasKey(
            'mappings',
            $capturedBody,
            'When all mapping fields are empty, the "mappings" key must be omitted from the body entirely'
        );
    }

    /**
     * When a nested field's only child has an empty-array value, the child is removed
     * and its parent's "properties" sub-key becomes empty and is also removed.
     * The parent itself must survive if it still has other non-array keys (e.g. "type").
     */
    public function testCreateIndexRemovesNestedEmptyMappingArrayFields(): void
    {
        $capturedBody = null;

        $service = $this->createService(
            client: $this->makeEmpty(SearchClientInterface::class, [
                'existsIndex' => false,
                'createIndex' => Expected::once(function (array $params) use (&$capturedBody): array {
                    $capturedBody = $params['body'];

                    return [];
                }),
            ])
        );

        $service->createIndex('test_index', [
            'parent_field' => [
                'type' => 'object',
                'properties' => [
                    'child_field' => [],
                ],
            ],
        ]);

        $this->assertNotNull($capturedBody, 'createIndex must have been called on the client');
        $this->assertArrayHasKey(
            'parent_field',
            $capturedBody['mappings']['properties'],
            'parent_field must still exist because it has a non-array "type" key'
        );
        $this->assertArrayNotHasKey(
            'properties',
            $capturedBody['mappings']['properties']['parent_field'],
            'The "properties" sub-key of parent_field must be removed when all its children are empty'
        );
    }

    /**
     * When putMapping() receives a properties array whose fields are all empty arrays,
     * normalization removes them and the "properties" key must be absent from the forwarded call.
     */
    public function testPutMappingRemovesEmptyArrayFieldsFromProperties(): void
    {
        $capturedParams = null;

        $service = $this->createService(
            client: $this->makeEmpty(SearchClientInterface::class, [
                'putIndexMapping' => Expected::once(function (array $params) use (&$capturedParams): array {
                    $capturedParams = $params;

                    return [];
                }),
            ])
        );

        $service->putMapping([
            'index' => 'test_index',
            'body' => [
                '_source' => ['enabled' => true],
                'properties' => ['empty_field' => []],
            ],
        ]);

        $this->assertNotNull($capturedParams, 'putIndexMapping must have been called on the client');
        $this->assertArrayNotHasKey(
            'properties',
            $capturedParams['body'],
            'When all properties fields are empty, the "properties" key must be omitted from the body'
        );
    }

    /**
     * When putMapping() receives a properties array with both valid and empty-array fields,
     * normalization removes only the empty ones while preserving the valid mapping fields.
     */
    public function testPutMappingPreservesValidFieldsWhileRemovingEmptyOnes(): void
    {
        $capturedParams = null;

        $service = $this->createService(
            client: $this->makeEmpty(SearchClientInterface::class, [
                'putIndexMapping' => Expected::once(function (array $params) use (&$capturedParams): array {
                    $capturedParams = $params;

                    return [];
                }),
            ])
        );

        $service->putMapping([
            'index' => 'test_index',
            'body' => [
                '_source' => ['enabled' => true],
                'properties' => [
                    'valid_field' => ['type' => 'keyword'],
                    'empty_field' => [],
                ],
            ],
        ]);

        $this->assertNotNull($capturedParams, 'putIndexMapping must have been called on the client');
        $this->assertArrayHasKey(
            'properties',
            $capturedParams['body'],
            'The "properties" key must still be present when at least one field has a valid mapping'
        );
        $this->assertArrayHasKey(
            'valid_field',
            $capturedParams['body']['properties'],
            'Valid mapping fields must be preserved after normalization'
        );
        $this->assertArrayNotHasKey(
            'empty_field',
            $capturedParams['body']['properties'],
            'Empty-array mapping fields must be removed by normalization'
        );
    }

    public function testGetCountAppliesSearchBodyProcessorsBeforeStrippingDisallowedKeys(): void
    {
        // Processors must keep the body valid for BOTH _search and _count — transform the query
        // subtree only (a top-level knn, for example, would be rejected by a real _count).
        $processor = $this->makeEmpty(SearchBodyProcessorInterface::class, [
            'process' => function (array $body, string $indexName): array {
                $body['query'] = ['term' => ['processed_for' => $indexName]];

                return $body;
            },
        ]);

        $service = $this->createService(
            client: $this->makeEmpty(SearchClientInterface::class, [
                'count' => Expected::once(function (array $params) {
                    $body = $params['body'];
                    $this->assertSame('test_index', $body['query']['term']['processed_for']);

                    return ['count' => 7];
                }),
            ]),
            searchBodyProcessors: [$processor],
        );

        $search = $this->makeEmpty(AdapterSearchInterface::class, [
            'toArray' => ['query' => ['match_all' => new stdClass()]],
        ]);

        $this->assertSame(7, $service->getCount($search, 'test_index'));
    }

    public function testGetCountAppliesSearchBodyProcessorsInIterationOrder(): void
    {
        $capturedBody = null;

        $appendProcessor = static function (string $marker) {
            return function (array $body) use ($marker): array {
                $body['trace'][] = $marker;

                return $body;
            };
        };

        $service = $this->createService(
            client: $this->makeEmpty(SearchClientInterface::class, [
                'count' => Expected::once(function (array $params) use (&$capturedBody) {
                    $capturedBody = $params['body'];

                    return ['count' => 1];
                }),
            ]),
            searchBodyProcessors: [
                $this->makeEmpty(SearchBodyProcessorInterface::class, ['process' => $appendProcessor('first')]),
                $this->makeEmpty(SearchBodyProcessorInterface::class, ['process' => $appendProcessor('second')]),
            ],
        );

        $search = $this->makeEmpty(AdapterSearchInterface::class, ['toArray' => []]);

        $service->getCount($search, 'test_index');

        $this->assertSame(['first', 'second'], $capturedBody['trace']);
    }

    public function testGetCountWithNoSearchBodyProcessorsLeavesBodyUnchanged(): void
    {
        $capturedBody = null;

        $service = $this->createService(
            client: $this->makeEmpty(SearchClientInterface::class, [
                'count' => Expected::once(function (array $params) use (&$capturedBody) {
                    $capturedBody = $params['body'];

                    return ['count' => 3];
                }),
            ]),
        );

        $search = $this->makeEmpty(AdapterSearchInterface::class, [
            'toArray' => ['query' => ['match_all' => new stdClass()]],
        ]);

        $service->getCount($search, 'test_index');

        $this->assertArrayHasKey('query', $capturedBody);
        $this->assertCount(1, $capturedBody);
    }

    /**
     * @param iterable<SearchBodyProcessorInterface> $searchBodyProcessors
     */
    private function createService(
        ?SearchIndexConfigServiceInterface $configService = null,
        ?SearchExecutionServiceInterface $searchExecutionService = null,
        ?IndexAliasServiceInterface $indexAliasService = null,
        ?SearchClientInterface $client = null,
        int $reindexMaxPolls = 10,
        int $reindexPollIntervalSeconds = 5,
        iterable $searchBodyProcessors = [],
    ): DefaultSearchService {
        return new DefaultSearchService(
            $configService ?? $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $searchExecutionService ?? $this->makeEmpty(SearchExecutionServiceInterface::class),
            $indexAliasService ?? $this->makeEmpty(IndexAliasServiceInterface::class),
            $client ?? $this->makeEmpty(SearchClientInterface::class),
            $reindexMaxPolls,
            $reindexPollIntervalSeconds,
            new SearchBodyProcessorPipeline($searchBodyProcessors),
        );
    }
}
