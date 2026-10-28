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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\RefreshIndexMode;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\BulkOperationException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexModeException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\BulkOperationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\SearchClient\SearchClientInterface;

/**
 * @internal
 */
final class BulkOperationService implements BulkOperationServiceInterface
{
    use LoggerAwareTrait;

    private const INDEX_NOT_FOUND_EXCEPTION = 'index_not_found_exception';

    private array $bulkOperationData = [];

    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly SynchronousProcessingServiceInterface $synchronousProcessing
    ) {
    }

    public function add(
        string $indexName,
        int $id,
        array $indexData,
        bool $upsert = true
    ): void {
        $this->bulkOperationData[] = [
            'index' => [
                '_index' => $indexName,
                '_id' => $id,
            ],
        ];

        $this->bulkOperationData[] = $indexData;
    }

    public function addUpdate(
        string $indexName,
        int $id,
        array $updateData
    ): void {
        $this->bulkOperationData[] = [
            'update' => [
                '_index' => $indexName,
                '_id' => $id,
            ],
        ];

        $this->bulkOperationData[] = [
            'doc' => $updateData,
            'doc_as_upsert' => true,
        ];
    }

    public function addDeletion(
        string $indexName,
        int $id
    ): void {
        $this->bulkOperationData[] = [
            'delete' => [
                '_index' => $indexName,
                '_id' => $id,
            ],
        ];
    }

    /**
     * @throws BulkOperationException
     */
    public function commit(?string $refreshIndex = null): void
    {
        if (!count($this->bulkOperationData)) {
            return;
        }

        if ($refreshIndex) {
            $this->validateRefreshIndexMode($refreshIndex);
        }

        try {
            $this->logger->info('Commit bulk to index.');

            $response = $this->client->bulk(
                $this->prepareBulkParams($refreshIndex)
            );

            $this->bulkOperationData = [];

            if (($response['errors'] ?? true) && !$this->hasOnlyDeletionsFromMissingIndices($response)) {
                $responseEncoded = json_encode($response, JSON_THROW_ON_ERROR);

                throw new BulkOperationException(
                    'Bulk operation produced errors: '. $responseEncoded
                );
            }
        } catch (Exception $e) {
            throw new BulkOperationException($e->getMessage());
        }
    }

    /**
     * A deletion from an index that no longer exists (e.g. the index of a deleted class, whose objects were
     * deleted with it) has nothing left to delete: it is done, not failed. Failing the request would leave the
     * queue entries behind, to fail again on every re-dispatch, and would block the other items of the bulk.
     * Any other error still fails the whole request.
     */
    private function hasOnlyDeletionsFromMissingIndices(array $response): bool
    {
        $missingIndices = [];
        foreach ($response['items'] ?? [] as $item) {
            foreach ($item as $action => $result) {
                if (!isset($result['error'])) {
                    continue;
                }

                if ($action !== 'delete' || ($result['error']['type'] ?? null) !== self::INDEX_NOT_FOUND_EXCEPTION) {
                    return false;
                }

                $missingIndices[$result['_index'] ?? 'unknown'] = true;
            }
        }

        // "errors" set without an item error to explain it: not a known harmless case
        if ($missingIndices === []) {
            return false;
        }

        $this->logger->notice('Bulk deletions from indices that no longer exist are treated as done.', [
            'indices' => array_keys($missingIndices),
        ]);

        return true;
    }

    private function prepareBulkParams(?string $refreshIndex): array
    {
        return [
            'body' => $this->bulkOperationData,
            'refresh' => $this->getRefreshMode($refreshIndex),
        ];
    }

    private function getRefreshMode(?string $refreshIndex): string
    {
        if ($refreshIndex) {
            return $refreshIndex;
        }

        if ($this->synchronousProcessing->isEnabled()) {
            return RefreshIndexMode::REFRESH->value;
        }

        return RefreshIndexMode::WAIT_FOR->value;
    }

    /**
     * @throws IndexModeException
     */
    private function validateRefreshIndexMode(string $refreshIndex): void
    {
        if (!in_array($refreshIndex, [
            RefreshIndexMode::REFRESH->value,
            RefreshIndexMode::NOT_REFRESH->value,
            RefreshIndexMode::WAIT_FOR->value,
        ], true)) {
            throw new IndexModeException(sprintf('Refresh Index parameter %s not valid', $refreshIndex));
        }
    }
}
