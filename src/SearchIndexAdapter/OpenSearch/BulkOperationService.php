<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch;

use Exception;
use OpenSearch\Client;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\RefreshIndexMode;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\BulkOperationException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexModeException;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\BulkOperationServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use RuntimeException;

/**
 * @internal
 */
final class BulkOperationService implements BulkOperationServiceInterface
{
    use LoggerAwareTrait;

    private array $bulkOperationData = [];

    public function __construct(
        private readonly Client $openSearchClient,
        private readonly SynchronousProcessingServiceInterface $synchronousProcessing
    ) {
    }

    public function addUpdate(
        string $indexName,
        int $id,
        array $indexData,
        bool $upsert = true
    ): void {
        $this->bulkOperationData[] = [
            'update' => [
                '_index' => $indexName,
                '_id' => $id,
            ],
        ];

        $this->bulkOperationData[] = [
            'doc' => $indexData,
            'doc_as_upsert' => $upsert,
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
        if(!count($this->bulkOperationData)) {
            return;
        }

        if ($refreshIndex) {
            $this->validateRefreshIndexMode($refreshIndex);
        }

        try {
            $this->logger->info('Commit bulk to index.');

            $response = $this->openSearchClient->bulk(
                $this->prepareBulkParams($refreshIndex)
            );

            $this->bulkOperationData = [];

            if ($response['errors'] ?? true) {
                $responseEncoded = json_encode($response, JSON_THROW_ON_ERROR);

                throw new RuntimeException(
                    'OpenSearch bulk produced errors: '. $responseEncoded
                );
            }
        } catch (Exception $e) {
            throw new BulkOperationException($e->getMessage());
        }
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
        if(!in_array($refreshIndex, [
            RefreshIndexMode::REFRESH->value,
            RefreshIndexMode::NOT_REFRESH->value,
            RefreshIndexMode::WAIT_FOR->value,
        ], true)) {
            throw new IndexModeException(sprintf('Refresh Index parameter %s not valid', $refreshIndex));
        }
    }
}
