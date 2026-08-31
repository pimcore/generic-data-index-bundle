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
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\SearchFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\Processor\SearchBodyProcessorInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search\SearchExecutionService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\SearchIndexAdapter\SearchResultDenormalizer;
use Pimcore\SearchClient\SearchClientInterface;

/**
 * @internal
 */
final class SearchExecutionServiceTest extends Unit
{
    public function testExecutedSearchesAreCollectedInDebugMode(): void
    {
        $service = $this->createService(debugMode: true);
        $search = $this->createSearch();

        $service->executeSearch($search, 'test_index');

        $executedSearches = $service->getExecutedSearches();
        $this->assertCount(1, $executedSearches);
        $searchInformation = $executedSearches[0];
        $this->assertTrue($searchInformation->isSuccess());
        $this->assertSame($search, $searchInformation->getSearch());
        $this->assertSame(0, $searchInformation->getResponse()['hits']['total']['value']);
        $this->assertIsNumeric($searchInformation->getExecutionTime());
        $this->assertNotEmpty($searchInformation->getStackTrace());
    }

    public function testExecutedSearchHistoryIsCappedInDebugMode(): void
    {
        $maxExecutedSearches = 500;
        $service = $this->createService(debugMode: true);

        $searches = [];
        for ($i = 0; $i < $maxExecutedSearches + 5; $i++) {
            $searches[] = $search = $this->createSearch();
            $service->executeSearch($search, 'test_index');
        }

        $executedSearches = $service->getExecutedSearches();
        $this->assertCount($maxExecutedSearches, $executedSearches);
        // the oldest entries are dropped, the most recent ones are kept
        $this->assertSame($searches[5], $executedSearches[0]->getSearch());
        $this->assertSame(
            $searches[$maxExecutedSearches + 4],
            $executedSearches[$maxExecutedSearches - 1]->getSearch()
        );
    }

    public function testExecutedSearchesAreNotCollectedOutsideDebugMode(): void
    {
        $service = $this->createService(debugMode: false);

        $service->executeSearch($this->createSearch(), 'test_index');

        $this->assertSame([], $service->getExecutedSearches());
    }

    public function testFailedSearchesAreCollectedInDebugMode(): void
    {
        $service = $this->createService(
            debugMode: true,
            client: $this->makeEmpty(SearchClientInterface::class, [
                'search' => static fn () => throw new Exception('search failed'),
            ])
        );

        try {
            $service->executeSearch($this->createSearch(), 'test_index');
            $this->fail('SearchFailedException was not thrown');
        } catch (SearchFailedException) {
            // expected
        }

        $executedSearches = $service->getExecutedSearches();
        $this->assertCount(1, $executedSearches);
        $this->assertFalse($executedSearches[0]->isSuccess());
    }

    public function testFailedSearchesAreNotCollectedOutsideDebugMode(): void
    {
        $service = $this->createService(
            debugMode: false,
            client: $this->makeEmpty(SearchClientInterface::class, [
                'search' => static fn () => throw new Exception('search failed'),
            ])
        );

        try {
            $service->executeSearch($this->createSearch(), 'test_index');
            $this->fail('SearchFailedException was not thrown');
        } catch (SearchFailedException) {
            // expected
        }

        $this->assertSame([], $service->getExecutedSearches());
    }

    public function testSearchBodyProcessorsTransformBodyBeforeSearch(): void
    {
        $capturedBody = null;

        $service = $this->createService(
            debugMode: false,
            client: $this->makeEmpty(SearchClientInterface::class, [
                'search' => function (array $params) use (&$capturedBody) {
                    $capturedBody = $params['body'];

                    return [
                        'hits' => ['hits' => [], 'total' => ['value' => 0], 'max_score' => null],
                    ];
                },
            ]),
            searchBodyProcessors: [$this->createProcessor('marker', 'processed')],
        );

        $service->executeSearch($this->createSearch(), 'test_index');

        $this->assertSame('processed', $capturedBody['marker']);
        // track_total_hits is appended after the processor chain has already run
        $this->assertTrue($capturedBody['track_total_hits']);
    }

    public function testSearchBodyProcessorsRunInIterationOrder(): void
    {
        $capturedBody = null;

        $service = $this->createService(
            debugMode: false,
            client: $this->makeEmpty(SearchClientInterface::class, [
                'search' => function (array $params) use (&$capturedBody) {
                    $capturedBody = $params['body'];

                    return [
                        'hits' => ['hits' => [], 'total' => ['value' => 0], 'max_score' => null],
                    ];
                },
            ]),
            searchBodyProcessors: [
                $this->createAppendingProcessor('first'),
                $this->createAppendingProcessor('second'),
            ],
        );

        $service->executeSearch($this->createSearch(), 'test_index');

        $this->assertSame(['first', 'second'], $capturedBody['trace']);
    }

    public function testNoSearchBodyProcessorsLeavesBodyUnchanged(): void
    {
        $capturedBody = null;

        $service = $this->createService(
            debugMode: false,
            client: $this->makeEmpty(SearchClientInterface::class, [
                'search' => function (array $params) use (&$capturedBody) {
                    $capturedBody = $params['body'];

                    return [
                        'hits' => ['hits' => [], 'total' => ['value' => 0], 'max_score' => null],
                    ];
                },
            ]),
        );

        $service->executeSearch($this->createSearch(), 'test_index');

        $this->assertSame(['track_total_hits' => true], $capturedBody);
    }

    /**
     * @param iterable<SearchBodyProcessorInterface> $searchBodyProcessors
     */
    private function createService(
        bool $debugMode,
        ?SearchClientInterface $client = null,
        iterable $searchBodyProcessors = [],
    ): SearchExecutionService {
        return new SearchExecutionService(
            new SearchResultDenormalizer(),
            $client ?? $this->makeEmpty(SearchClientInterface::class, [
                'search' => [
                    'hits' => [
                        'hits' => [],
                        'total' => ['value' => 0],
                        'max_score' => null,
                    ],
                ],
            ]),
            $debugMode,
            $searchBodyProcessors,
        );
    }

    private function createSearch(): AdapterSearchInterface
    {
        return $this->makeEmpty(AdapterSearchInterface::class, [
            'toArray' => [],
            'isReverseItemOrder' => false,
        ]);
    }

    private function createProcessor(string $key, mixed $value): SearchBodyProcessorInterface
    {
        return $this->makeEmpty(SearchBodyProcessorInterface::class, [
            'process' => function (array $body) use ($key, $value): array {
                $body[$key] = $value;

                return $body;
            },
        ]);
    }

    private function createAppendingProcessor(string $marker): SearchBodyProcessorInterface
    {
        return $this->makeEmpty(SearchBodyProcessorInterface::class, [
            'process' => function (array $body) use ($marker): array {
                $body['trace'][] = $marker;

                return $body;
            },
        ]);
    }
}
