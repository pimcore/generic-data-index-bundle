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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\Search;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\ResultWindowTooLargeException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\SearchFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\DefaultSearch\Debug\SearchInformation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\SearchIndexAdapter\SearchResultDenormalizer;
use Pimcore\SearchClient\SearchClientInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @internal
 */
final class SearchExecutionService implements SearchExecutionServiceInterface
{
    /**
     * @var SearchInformation[]
     */
    private array $executedSearches = [];

    public function __construct(
        private readonly SearchResultDenormalizer $searchResultDenormalizer,
        private readonly SearchClientInterface $client,
        private readonly bool $debugMode,
    ) {
    }

    /**
     * @throws SearchFailedException
     */
    public function executeSearch(
        AdapterSearchInterface $search,
        string $indexName,
        int|bool|null $trackTotalHits = true
    ): SearchResult {
        try {
            $stopWatch = new Stopwatch();
            $stopWatch->start('search');

            $searchParams = [
                'index' => $indexName,
                'body' => $search->toArray(),
            ];

            if ($trackTotalHits !== null) {
                $searchParams['body']['track_total_hits'] = $trackTotalHits;
            }

            $defaultSearchResult = $this
                ->client
                ->search($searchParams);

            $executionTime = $stopWatch->stop('search')->getDuration();
        } catch (Exception $e) {
            $searchInformation = new SearchInformation(
                $search,
                false,
                [],
                0,
                []
            );

            if ($this->debugMode) {
                $this->executedSearches[] = $searchInformation;
            }

            if ($this->isWindowTooLarge($e)) {
                throw new ResultWindowTooLargeException(
                    $searchInformation,
                    'Result window too large: ' . $e->getMessage(),
                    $e->getCode(),
                    $e
                );
            }

            throw new SearchFailedException(
                $searchInformation,
                'Search failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        if ($search->isReverseItemOrder()) {
            $defaultSearchResult['hits']['hits'] = array_reverse($defaultSearchResult['hits']['hits']);
        }

        if ($this->debugMode) {
            $this->executedSearches[] = new SearchInformation(
                $search,
                true,
                $defaultSearchResult,
                $executionTime,
                debug_backtrace(),
            );
        }

        return $this->searchResultDenormalizer->denormalize(
            $defaultSearchResult,
            SearchResult::class,
            null,
            ['search' => $search]
        );
    }

    public function getExecutedSearches(): array
    {
        return $this->executedSearches;
    }

    private function isWindowTooLarge(Exception $e): bool
    {
        try {
            if (str_contains($e->getMessage(), 'window is too large')) {
                return true;
            }

        } catch (Exception) {
            return false;
        }

        return false;
    }
}
