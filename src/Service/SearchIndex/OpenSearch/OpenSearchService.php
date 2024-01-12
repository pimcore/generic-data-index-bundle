<?php

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

use OpenSearch\Client;
use OpenSearch\Common\Exceptions\Missing404Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigService;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Psr\Log\LogLevel;

class OpenSearchService
{
    const INDEX_VERION_ODD = 'odd';

    const INDEX_VERION_EVEN = 'even';

    use LoggerAwareTrait;

    private Client $openSearchClient;

    public function __construct(
        private SearchIndexConfigService $searchIndexConfigService,
    ) {
        $this->openSearchClient = (new \OpenSearch\ClientBuilder())
            ->setHosts(['https://opensearch:9200'])
            ->setBasicAuthentication('admin', 'admin')
            ->setSSLVerification(false)
            ->build();
    }

    public function refreshIndex(string $indexName): array
    {
        $this->logger->info("Refreshing index $indexName");

        return $this->openSearchClient
            ->indices()
            ->refresh(['index' => $indexName]);
    }

    public function deleteIndex($indexName, bool $silent = false)
    {
        try {
            $this->logger->log($silent ? LogLevel::DEBUG : LogLevel::INFO, "Deleting index $indexName");
            $response = $this->openSearchClient->indices()->delete(['index' => $indexName]);
            $this->logger->debug(json_encode($response));
        } catch (Missing404Exception $e) {
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
        } catch (Missing404Exception $e) {
            return '';
        }

        $aliasIndexName = array_key_first($result);
        $nameParts = explode('-', $aliasIndexName);

        return end($nameParts);
    }

    /**
     * @throws \Exception
     */
    public function reindex(string $indexName, array $mapping): void
    {
        $currentIndexVersion = $this->getCurrentIndexVersion($indexName);

        $oldIndexName = $indexName . '-' . $currentIndexVersion;
        $newIndexName = $indexName . '-' . ($currentIndexVersion === self::INDEX_VERION_EVEN ? self::INDEX_VERION_ODD : self::INDEX_VERION_EVEN);
        $this->createIndex($indexName . '-' . ($currentIndexVersion === self::INDEX_VERION_EVEN ? self::INDEX_VERION_EVEN : self::INDEX_VERION_EVEN), $mapping);

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
        } catch (\Exception $e) {
            $this->logger->error('Reindexing failed due to following error: ' . $e);
        }

        $this->switchIndexAliasAndCleanup($indexName, $oldIndexName, $newIndexName);
    }

    /**
     * @throws \Exception
     */
    private function switchIndexAliasAndCleanup(string $aliasName, string $oldIndexName, string $newIndexName)
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
            throw new \Exception('Switching Alias failed for ' . $newIndexName);
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
        } catch (\Exception $e) {
            $this->logger->error($e);
        }

        return $this;
    }

    public function addAlias(string $indexName, string $aliasName): OpenSearchService
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

    public function getOpenSearchClient(): Client
    {
        return $this->openSearchClient;
    }
}
