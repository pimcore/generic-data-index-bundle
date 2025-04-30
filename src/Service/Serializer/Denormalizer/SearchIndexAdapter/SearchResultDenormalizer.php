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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\Serializer\Denormalizer\SearchIndexAdapter;

use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResult;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultAggregation;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultAggregationBucket;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndexAdapter\SearchResultHit;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class SearchResultDenormalizer implements DenormalizerInterface
{
    /**
     * @param array $data
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): SearchResult
    {
        $searchResult = $data;

        return new SearchResult(
            hits: $this->hydrateHits($searchResult['hits']['hits']),
            aggregations: $this->hydrateAggregations($searchResult['aggregations'] ?? []),
            totalHits: $searchResult['hits']['total']['value'],
            maxScore: $searchResult['hits']['max_score'],
            search: $context['search'],
            response: $searchResult,
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null): bool
    {
        return is_array($data) && is_subclass_of($type, SearchResult::class);
    }

    /**
     * @return SearchResultHit[]
     */
    private function hydrateHits(array $hits): array
    {
        $result = [];

        foreach ($hits as $hit) {
            $result[] = new SearchResultHit(
                id: $hit['_id'],
                index: $hit['_index'],
                score: $hit['_score'],
                source: $hit['_source'] ?? [],
                sort: $hit['sort'] ?? null,
            );
        }

        return $result;
    }

    /**
     * @return SearchResultAggregation[]
     */
    private function hydrateAggregations(array $aggregations): array
    {
        $result = [];
        foreach ($aggregations as $name => $aggregation) {
            $result[] = new SearchResultAggregation(
                name: $name,
                buckets: $this->hydrateAggregationBuckets($aggregation['buckets'] ?? []),
                otherDocCount: $aggregation['sum_other_doc_count'] ?? 0,
                docCountErrorUpperBound: $aggregation['doc_count_error_upper_bound'] ?? 0,
                aggregationResult: $aggregation,
            );
        }

        return $result;
    }

    /**
     * @return SearchResultAggregationBucket[]
     */
    private function hydrateAggregationBuckets(array $buckets): array
    {
        $result = [];
        foreach ($buckets as $bucket) {
            $result[] = new SearchResultAggregationBucket(
                key: $bucket['key'],
                docCount: $bucket['doc_count'],
            );
        }

        return $result;
    }
}
