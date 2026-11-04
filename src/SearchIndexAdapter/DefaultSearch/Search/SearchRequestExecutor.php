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

use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Applies the configured full-text search fields to the request, repeats it with explicit fields if the
 * field expansion limit is exceeded and logs failed shards, which the search engine otherwise only reports
 * inside an apparently successful response.
 *
 * @internal
 */
final readonly class SearchRequestExecutor implements SearchRequestExecutorInterface
{
    public function __construct(
        private SearchIndexConfigServiceInterface $searchIndexConfigService,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(callable $request, array $params): array
    {
        $fullTextSearchFields = $this->searchIndexConfigService->getSearchSettings()['full_text_search_fields'] ?? [];
        if ($fullTextSearchFields !== [] && isset($params['body'])) {
            $params['body'] = FieldExpansionFallback::restrictFullTextFields($params['body'], $fullTextSearchFields)
                ?? $params['body'];
        }

        $response = FieldExpansionFallback::execute($request, $params);
        $this->logFailedShards($params, $response);

        return $response;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $response
     */
    private function logFailedShards(array $params, array $response): void
    {
        $failedShards = (int) ($response['_shards']['failed'] ?? 0);
        if ($failedShards === 0) {
            return;
        }

        $this->logger->warning(
            'Search request on index "{index}" is incomplete: {failedShards} of {totalShards} shards failed.',
            [
                'index' => $params['index'] ?? '',
                'failedShards' => $failedShards,
                'totalShards' => (int) ($response['_shards']['total'] ?? 0),
                'reasons' => $this->getFailureReasons($response['_shards']['failures'] ?? []),
            ]
        );
    }

    /**
     * @return string[]
     */
    private function getFailureReasons(mixed $failures): array
    {
        if (!is_array($failures)) {
            return [];
        }

        $reasons = [];
        foreach ($failures as $failure) {
            $reasons[] = implode(': ', array_filter([
                $failure['index'] ?? null,
                $failure['reason']['type'] ?? null,
                $failure['reason']['reason'] ?? null,
            ], static fn (mixed $part) => is_string($part) && $part !== ''));
        }

        return array_values(array_unique($reasons));
    }
}
