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

    /**
     * How long the cluster keeps the scroll snapshot between two pages - the default of the native API, which
     * pages with a scroll, too.
     */
    private const string SCROLL_KEEP_ALIVE = '5m';

    /** Bulk item failures quoted in the exception message. */
    private const int MAX_REPORTED_FAILURES = 5;

    private bool $emulationForced = false;

    /** Set once the cluster refused _update_by_query, so that each later request skips the native attempt. */
    private bool $nativeApiForbidden = false;

    /** Set once the cluster refused to open a scroll, so that each later emulation pages with search_after. */
    private bool $scrollForbidden = false;

    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
        private readonly int $batchSize = self::BATCH_SIZE,
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
     * Reads the matching documents page by page and writes the transformed sources back in bulk. Each request
     * is short, so neither a proxy timeout nor a long-running task is involved.
     *
     * @throws BulkOperationException
     */
    private function emulate(array $params, callable $transform): void
    {
        $index = $params['index'];
        $query = $params['body']['query'] ?? ['match_all' => new stdClass()];
        $proceedOnConflicts = ($params['conflicts'] ?? null) === 'proceed';

        $writePage = function (array $hits) use ($index, $transform, $proceedOnConflicts): void {
            $operations = $this->buildWriteOperations($hits, $transform);

            if ($operations !== []) {
                $this->assertBulkSucceeded($index, $this->client->bulk(['body' => $operations]), $proceedOnConflicts);
            }
        };

        $originalClient = $this->scrollForbidden ? null : $this->getScrollClient();

        if ($originalClient === null || !$this->pageWithScroll($originalClient, $index, $query, $writePage)) {
            $this->pageWithSearchAfter($index, $query, $writePage);
        }

        if ($params['refresh'] ?? false) {
            $this->client->refreshIndex(['index' => $index]);
        }
    }

    /**
     * Reads all pages from one scroll, i.e. from the snapshot the cluster takes for the first page, as the
     * native API does. A document changed after that still comes with the sequence number it had then, so
     * writing it back is a version conflict instead of overwriting the change. The scroll is cleared however
     * the run ends.
     *
     * @param callable(array): void $writePage
     *
     * @return bool false if the cluster refused to open the scroll with HTTP 403 - nothing is written then
     *
     * @throws BulkOperationException
     */
    private function pageWithScroll(object $originalClient, string $index, array $query, callable $writePage): bool
    {
        try {
            $response = $this->client->search([
                'index' => $index,
                'scroll' => self::SCROLL_KEEP_ALIVE,
                'body' => [
                    'size' => $this->batchSize,
                    'seq_no_primary_term' => true,
                    'query' => $query,
                    'sort' => ['_doc'],
                ],
            ]);
        } catch (Exception $exception) {
            if (!$this->isForbidden($exception)) {
                throw $exception;
            }

            $this->scrollForbidden = true;
            $this->logger->warning(sprintf(
                'The search cluster refused a scroll on "%s" (%s). Emulated update by query pages with '
                . 'search_after for the rest of this process, without a snapshot.',
                $index,
                $exception->getMessage()
            ));

            return false;
        }

        $scrollId = $response['_scroll_id'] ?? null;

        try {
            while (($hits = $this->getCompleteHits($index, $response)) !== []) {
                $writePage($hits);

                $response = $this->toArray($index, $originalClient->scroll([
                    'body' => ['scroll_id' => $scrollId, 'scroll' => self::SCROLL_KEEP_ALIVE],
                ]));
                $scrollId = $response['_scroll_id'] ?? $scrollId;
            }
        } finally {
            $this->clearScroll($originalClient, $scrollId);
        }

        return true;
    }

    /**
     * Fallback for a client that does not expose the scroll API, or a cluster that refuses it. Pages with
     * search_after, sorted by the element id: an id is unique within an element type, so also across the indices
     * behind an alias, and documents that stop matching the query once they are written are behind the cursor
     * already. There is no snapshot, though: a document changed before its page is read is read - and written -
     * in its changed state.
     *
     * @param callable(array): void $writePage
     *
     * @throws BulkOperationException
     */
    private function pageWithSearchAfter(string $index, array $query, callable $writePage): void
    {
        $searchAfter = null;

        do {
            $body = [
                'size' => $this->batchSize,
                'track_total_hits' => false,
                'seq_no_primary_term' => true,
                'query' => $query,
                'sort' => [[SystemField::ID->getPath() => ['order' => 'asc']]],
            ];

            if ($searchAfter !== null) {
                $body['search_after'] = $searchAfter;
            }

            $hits = $this->getCompleteHits($index, $this->client->search(['index' => $index, 'body' => $body]));

            if ($hits === []) {
                return;
            }

            $writePage($hits);

            $nextSearchAfter = $hits[array_key_last($hits)]['sort'] ?? null;

            if ($nextSearchAfter === null || $nextSearchAfter === $searchAfter) {
                throw new BulkOperationException(sprintf(
                    'Emulated update by query on "%s" stopped making progress: the page carries no new sort value.',
                    $index
                ));
            }

            $searchAfter = $nextSearchAfter;
        } while (count($hits) === $this->batchSize);
    }

    /**
     * @param callable(array): array $transform
     */
    private function buildWriteOperations(array $hits, callable $transform): array
    {
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

        return $operations;
    }

    /**
     * A page can come back with HTTP 200 and still be partial: timed out, or without the hits of failed
     * shards. Writing it and moving on would leave the omitted documents unchanged without any error.
     *
     * @throws BulkOperationException
     */
    private function getCompleteHits(string $index, array $response): array
    {
        if (($response['timed_out'] ?? false) === true) {
            throw new BulkOperationException(sprintf(
                'Emulated update by query on "%s" aborted: the search timed out and returned a partial page.',
                $index
            ));
        }

        $failedShards = (int) ($response['_shards']['failed'] ?? 0);

        if ($failedShards > 0) {
            throw new BulkOperationException(sprintf(
                'Emulated update by query on "%s" aborted: %d shard(s) failed, the page is partial.',
                $index,
                $failedShards
            ));
        }

        return $response['hits']['hits'] ?? [];
    }

    /**
     * The scroll API is not part of SearchClientInterface. The bundled OpenSearch and Elasticsearch clients
     * expose the client they wrap, which has it.
     */
    private function getScrollClient(): ?object
    {
        if (!is_callable([$this->client, 'getOriginalClient'])) {
            return null;
        }

        $originalClient = $this->client->getOriginalClient();

        if (!is_object($originalClient)
            || !method_exists($originalClient, 'scroll')
            || !method_exists($originalClient, 'clearScroll')
        ) {
            return null;
        }

        return $originalClient;
    }

    /**
     * The Elasticsearch client answers with a response object, the OpenSearch client with an array.
     *
     * @throws BulkOperationException
     */
    private function toArray(string $index, mixed $response): array
    {
        if (is_object($response) && method_exists($response, 'asArray')) {
            $response = $response->asArray();
        }

        if (!is_array($response)) {
            throw new BulkOperationException(sprintf(
                'Emulated update by query on "%s" got an unexpected scroll response of type %s.',
                $index,
                get_debug_type($response)
            ));
        }

        return $response;
    }

    private function clearScroll(object $originalClient, ?string $scrollId): void
    {
        if ($scrollId === null) {
            return;
        }

        try {
            $originalClient->clearScroll(['body' => ['scroll_id' => $scrollId]]);
        } catch (Throwable $exception) {
            // The cluster drops the scroll itself once its keep-alive expires.
            $this->logger->warning('Could not clear the scroll of an emulated update by query: '
                . $exception->getMessage());
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
                || preg_match('/"status"\s*:\s*403\b|\b403 Forbidden\b/u', $current->getMessage()) === 1
            ) {
                return true;
            }
        }

        return false;
    }
}
