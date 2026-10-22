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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\FieldCategory\SystemField;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\BulkOperationException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\UpdateByQueryServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\SearchClient\SearchClientInterface;
use stdClass;
use Throwable;

/**
 * @internal
 */
final class UpdateByQueryService implements UpdateByQueryServiceInterface
{
    use LoggerAwareTrait;

    /** Documents per search/bulk round trip of the emulation. */
    private const int BATCH_SIZE = 500;

    /** Bulk item failures quoted in the exception message. */
    private const int MAX_REPORTED_FAILURES = 5;

    private bool $emulationForced = false;

    /** Set once the cluster refused _update_by_query, so that each later request skips the native attempt. */
    private bool $nativeApiForbidden = false;

    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
    ) {
    }

    public function updateByQuery(array $params, callable $transform): void
    {
        if ($this->emulationForced
            || $this->nativeApiForbidden
            || $this->searchIndexConfigService->emulateUpdateByQuery()
        ) {
            $this->emulate($params, $transform);

            return;
        }

        try {
            $this->client->updateByQuery($params);

            return;
        } catch (Exception $exception) {
            if (!$this->isForbidden($exception)) {
                throw $exception;
            }
        }

        // The cluster refuses the request before touching any document, so nothing is left half done.
        $this->nativeApiForbidden = true;
        $this->logger->warning(sprintf(
            'The search cluster refused _update_by_query on "%s" (%s). Emulating it with search and bulk '
            . 'requests for the rest of this process. Set search_settings.emulate_update_by_query to skip '
            . 'the native attempt.',
            $params['index'] ?? '',
            $exception->getMessage()
        ));

        $this->emulate($params, $transform);
    }

    public function forceEmulation(bool $force): void
    {
        $this->emulationForced = $force;
    }

    /**
     * Pages through the matching documents with search_after and writes the transformed sources back in bulk.
     * Each request is short, so neither a proxy timeout nor a long-running task is involved. Sorting by the
     * element id keeps the paging stable while documents change: an id is unique within an element type, so
     * also across the indices behind an alias, and documents that stop matching the query once they are
     * written are behind the cursor already.
     *
     * @throws BulkOperationException
     */
    private function emulate(array $params, callable $transform): void
    {
        $index = $params['index'];
        $proceedOnConflicts = ($params['conflicts'] ?? null) === 'proceed';
        $searchAfter = null;

        do {
            $body = [
                'size' => self::BATCH_SIZE,
                'track_total_hits' => false,
                'seq_no_primary_term' => true,
                'query' => $params['body']['query'] ?? ['match_all' => new stdClass()],
                'sort' => [[SystemField::ID->getPath() => ['order' => 'asc']]],
            ];

            if ($searchAfter !== null) {
                $body['search_after'] = $searchAfter;
            }

            $hits = $this->client->search(['index' => $index, 'body' => $body])['hits']['hits'] ?? [];

            if ($hits === []) {
                break;
            }

            $operations = [];
            foreach ($hits as $hit) {
                $source = $hit['_source'] ?? [];
                $newSource = $transform($source);

                if ($newSource === $source) {
                    continue;
                }

                $operations[] = [
                    'index' => [
                        '_index' => $hit['_index'],
                        '_id' => $hit['_id'],
                        'if_seq_no' => $hit['_seq_no'],
                        'if_primary_term' => $hit['_primary_term'],
                    ],
                ];
                $operations[] = $newSource;
            }

            if ($operations !== []) {
                $this->assertBulkSucceeded($index, $this->client->bulk(['body' => $operations]), $proceedOnConflicts);
            }

            $nextSearchAfter = $hits[array_key_last($hits)]['sort'] ?? null;

            if ($nextSearchAfter === null || $nextSearchAfter === $searchAfter) {
                throw new BulkOperationException(sprintf(
                    'Emulated update by query on "%s" stopped making progress: the page carries no new sort value.',
                    $index
                ));
            }

            $searchAfter = $nextSearchAfter;
        } while (count($hits) === self::BATCH_SIZE);

        if ($params['refresh'] ?? false) {
            $this->client->refreshIndex(['index' => $index]);
        }
    }

    /**
     * @throws BulkOperationException
     */
    private function assertBulkSucceeded(string $index, array $response, bool $proceedOnConflicts): void
    {
        if (!($response['errors'] ?? false)) {
            return;
        }

        $failures = [];
        $failureCount = 0;
        foreach ($response['items'] ?? [] as $item) {
            $result = reset($item);

            if (!is_array($result) || !isset($result['error'])) {
                continue;
            }

            // A document changed since it was read - with conflicts=proceed the native API skips it, too.
            if ($proceedOnConflicts && ($result['status'] ?? null) === 409) {
                continue;
            }

            $failureCount++;
            if (count($failures) < self::MAX_REPORTED_FAILURES) {
                $failures[] = ['_id' => $result['_id'] ?? null, 'error' => $result['error']];
            }
        }

        if ($failureCount === 0) {
            return;
        }

        throw new BulkOperationException(sprintf(
            'Emulated update by query on "%s" failed for %d document(s): %s',
            $index,
            $failureCount,
            json_encode($failures, JSON_PARTIAL_OUTPUT_ON_ERROR)
        ));
    }

    /**
     * The bundled search clients wrap the transport exception without its status code, but keep its
     * message: the error response body (whose "status" is 403), or "403 Forbidden: ..." for Elasticsearch.
     */
    private function isForbidden(Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current->getCode() === 403
                || preg_match('/"status"\s*:\s*403\b|\b403 Forbidden\b/', $current->getMessage()) === 1
            ) {
                return true;
            }
        }

        return false;
    }
}
