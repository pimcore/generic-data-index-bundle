<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch\Search;

use Exception;
use OpenSearch\Client;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\OpenSearch\SearchFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Debug\SearchInformation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\AdapterSearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\SearchIndexAdapter\SearchResultDenormalizer;
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
        private readonly Client $openSearchClient,
    ) {
    }

    /**
     * @throws SearchFailedException
     */
    public function executeSearch(AdapterSearchInterface $search, string $indexName): SearchResult
    {
        try {
            $stopWatch = new Stopwatch();
            $stopWatch->start('search');

            $openSearchResult = $this
                ->openSearchClient
                ->search([
                    'index' => $indexName,
                    'body' => $search->toArray(),
                ]);

            $executionTime = $stopWatch->stop('search')->getDuration();

        } catch (Exception $e) {
            $searchInformation = new SearchInformation(
                $search,
                false,
                [],
                0,
                []
            );

            $this->executedSearches[] = $searchInformation;

            throw new SearchFailedException(
                $searchInformation,
                'Search failed: ' . $e->getMessage(),
                $e->getCode(), $e
            );
        }

        $this->executedSearches[] = new SearchInformation(
            $search,
            true,
            $openSearchResult,
            $executionTime,
            debug_backtrace(),
        );

        return $this->searchResultDenormalizer->denormalize(
            $openSearchResult,
            SearchResult::class,
            null,
            ['search' => $search]
        );
    }

    public function getExecutedSearches(): array
    {
        return $this->executedSearches;
    }
}
