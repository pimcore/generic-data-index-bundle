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

    private function createService(
        bool $debugMode,
        ?SearchClientInterface $client = null,
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
        );
    }

    private function createSearch(): AdapterSearchInterface
    {
        return $this->makeEmpty(AdapterSearchInterface::class, [
            'toArray' => [],
            'isReverseItemOrder' => false,
        ]);
    }
}
