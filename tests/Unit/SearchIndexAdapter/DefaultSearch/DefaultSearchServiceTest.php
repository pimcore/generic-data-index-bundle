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
use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DefaultSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\FieldExpansionFallback;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\SearchExecutionServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\SearchClient\SearchClientInterface;

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

    public function testGetCountIsRepeatedWithExplicitFieldsWhenFieldExpansionFails(): void
    {
        $bodies = [];
        $service = $this->createService(
            client: $this->makeEmpty(SearchClientInterface::class, [
                'count' => function (array $params) use (&$bodies) {
                    $bodies[] = $params['body'];
                    if (count($bodies) === 1) {
                        throw new Exception('field expansion matches too many fields, limit: 4096, got: 82499');
                    }

                    return ['count' => 3];
                },
            ])
        );

        $search = $this->makeEmpty(AdapterSearchInterface::class, [
            'toArray' => ['query' => ['bool' => ['filter' => ['simple_query_string' => ['query' => 'pro']]]]],
        ]);

        $this->assertSame(3, $service->getCount($search, 'test_index'));
        $this->assertCount(2, $bodies);
        $this->assertSame(
            FieldExpansionFallback::FIELDS,
            $bodies[1]['query']['bool']['filter']['simple_query_string']['fields']
        );
    }

    private function createService(
        ?SearchIndexConfigServiceInterface $configService = null,
        ?SearchExecutionServiceInterface $searchExecutionService = null,
        ?IndexAliasServiceInterface $indexAliasService = null,
        ?SearchClientInterface $client = null,
        int $reindexMaxPolls = 10,
        int $reindexPollIntervalSeconds = 5,
    ): DefaultSearchService {
        return new DefaultSearchService(
            $configService ?? $this->makeEmpty(SearchIndexConfigServiceInterface::class),
            $searchExecutionService ?? $this->makeEmpty(SearchExecutionServiceInterface::class),
            $indexAliasService ?? $this->makeEmpty(IndexAliasServiceInterface::class),
            $client ?? $this->makeEmpty(SearchClientInterface::class),
            $reindexMaxPolls,
            $reindexPollIntervalSeconds,
        );
    }
}
