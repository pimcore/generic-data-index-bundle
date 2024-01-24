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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch;

use Exception;
use OpenSearch\Client;
use OpenSearch\Common\Exceptions\Missing404Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Psr\Log\LogLevel;

class OpenSearchService
{
    private const INDEX_VERION_ODD = 'odd';

    private const INDEX_VERION_EVEN = 'even';

    use LoggerAwareTrait;

    public function __construct(
        private readonly SearchIndexConfigService $searchIndexConfigService,
        private readonly Client $openSearchClient,
    ) {
    }

    public function refreshIndex(string $indexName): array
    {
        $this->logger->info("Refreshing index $indexName");

        return $this->openSearchClient
            ->indices()
            ->refresh(['index' => $indexName]);
    }

    public function deleteIndex($indexName, bool $silent = false): OpenSearchService
    {
        try {
            $this->logger->log($silent ? LogLevel::DEBUG : LogLevel::INFO, "Deleting index $indexName");
            $response = $this->openSearchClient->indices()->delete(['index' => $indexName]);
            $this->logger->debug(json_encode($response));
        } catch (Missing404Exception) {
            $this->logger->debug('Delete index - index did not exist: ' . $indexName);
        }

        return $this;
    }

    public function getCurrentIndexVersion(string $indexName): string
    {
        try {
            $result = $this->openSearchClient->indices()->getAlias([
                'name' => $indexName,
            ]);
        } catch (Missing404Exception) {
            return '';
        }

        $aliasIndexName = array_key_first($result);
        $nameParts = explode('-', $aliasIndexName);

        return end($nameParts);
    }

    /**
     * @throws Exception
     */
    public function reindex(string $indexName, array $mapping): void
    {
        $currentIndexVersion = $this->getCurrentIndexVersion($indexName);
        $newIndexVersion = $currentIndexVersion === self::INDEX_VERION_EVEN
            ? self::INDEX_VERION_ODD
            : self::INDEX_VERION_EVEN;

        $oldIndexName = $indexName . '-' . $currentIndexVersion;
        $newIndexName = $indexName . '-' . $newIndexVersion;

        $this->createIndex($newIndexName, $mapping);

        $body = [
            'source' => [
                'index' => $oldIndexName,

            ],
            'dest' => [
                'index' => $newIndexName,
            ],
        ];

        try {
            $this->openSearchClient->reindex([
                'body' => $body,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Reindexing failed due to following error: ' . $e);
        }

        $this->switchIndexAliasAndCleanup($indexName, $oldIndexName, $newIndexName);
    }

    /**
     * @throws Exception
     */
    private function switchIndexAliasAndCleanup(string $aliasName, string $oldIndexName, string $newIndexName): void
    {
        $params['body'] = [
            'actions' => [
                [
                    'remove' => [
                        'index' => '*',
                        'alias' => $aliasName,
                    ],
                ],
                [
                    'add' => [
                        'index' => $newIndexName,
                        'alias' => $aliasName,
                    ],
                ],
            ],
        ];
        $result = $this->openSearchClient->indices()->updateAliases($params);
        if (!$result['acknowledged']) {
            throw new Exception('Switching Alias failed for ' . $newIndexName);
        }

        $this->deleteIndex($oldIndexName);
    }

    public function createIndex(string $indexName, array $mappings = null): OpenSearchService
    {
        $this->deleteIndex($indexName, true);

        try {
            $this->logger->info("Creating index $indexName");

            $body = [];
            if(!empty($this->searchIndexConfigService->getIndexSettings())) {
                $body['settings'] = $this->searchIndexConfigService->getIndexSettings();
            }

            if ($mappings) {
                $body['mappings']['properties'] = $mappings['body']['properties'];
                $body['mappings']['_source'] = $mappings['body']['_source'];
            }

            $response = $this->openSearchClient->indices()->create(
                [
                    'index' => $indexName,
                    'body' => $body,
                ]
            );

            $this->logger->debug(json_encode($response));
        } catch (Exception $e) {
            $this->logger->error($e);
        }

        return $this;
    }

    public function addAlias(string $aliasName, string $indexName): OpenSearchService
    {
        $params['body'] = [
            'actions' => [
                [
                    'add' => [
                        'index' => $indexName,
                        'alias' => $aliasName,
                    ],
                ],
            ],
        ];
        $this->openSearchClient->indices()->updateAliases($params);

        return $this;
    }

    public function putAlias(string $aliasName, string $indexName): array
    {
        return $this->openSearchClient->indices()->putAlias([
            'name' => $aliasName,
            'index' => $indexName,
        ]);
    }

    public function existsAlias(string $aliasName, string $indexName = null): bool
    {
        return $this->openSearchClient->indices()->existsAlias([
            'name' => $aliasName,
            'index' => $indexName,
        ]);
    }

    public function deleteAlias(string $indexName, string $aliasName): array
    {
        return $this->openSearchClient->indices()->deleteAlias([
            'name' => $aliasName,
            'index' => $indexName,
        ]);
    }

    public function getDocument(string $index, int $id): array
    {
        $params = [
            'index' => $index,
            'id' => $id,
        ];

        return $this->openSearchClient->get($params);
    }

    public function putMapping(array $params): array
    {
        return $this->openSearchClient->indices()->putMapping($params);
    }

    public function countByAttributeValue(string $indexName, string $attribute, string $value): int
    {
        $countResult = $this->openSearchClient->search([
            'index' => $indexName,
            'track_total_hits' => true,
            'rest_total_hits_as_int' => true,
            'body' => [
                'query' => [
                    'term' => [
                        $attribute => $value,
                    ],
                ],
                'size' => 0,
            ],
        ]);

        return $countResult['hits']['total'] ?? 0;
    }

    public function getOpenSearchClient(): Client
    {
        return $this->openSearchClient;
    }
}
