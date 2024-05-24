<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\OpenSearch;

use OpenSearch\Client;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;

/**
 * @internal
 */
final class IndexAliasService implements IndexAliasServiceInterface
{
    public function __construct(
        private readonly Client $openSearchClient,
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
    )
    {
    }

    public function addAlias(string $aliasName, string $indexName): array
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
        p_r($params);
        return $this->openSearchClient->indices()->updateAliases($params);
    }

    public function existsAlias(string $aliasName, string $indexName = null): bool
    {
        return $this->openSearchClient->indices()->existsAlias([
            'name' => $aliasName,
            'index' => $indexName,
            'client' => [
                'ignore' => [404],
            ],
        ]);
    }

    public function getAllAliases(): array
    {
        return $this->openSearchClient->cat()->aliases([
            'name' => $this->searchIndexConfigService->getIndexPrefix() . '*'
        ]);
    }

    public function deleteAlias(string $indexName, string $aliasName): array
    {
        return $this->openSearchClient->indices()->deleteAlias([
            'name' => $aliasName,
            'index' => $indexName,
        ]);
    }

    public function updateAliases(string $alias, array $indexNames, array $existingIndexNames = []): ?array
    {
        $toAdd = array_values(array_diff($indexNames, $existingIndexNames));
        $toRemove = array_values(array_diff($existingIndexNames, $indexNames));

        $actions = [];
        foreach ($toAdd as $index) {
            $actions[] = [
                'add' => [
                    'index' => $index,
                    'alias' => $alias
                ]
            ];
        }

        foreach ($toRemove as $index) {
            $actions[] = [
                'remove' => [
                    'index' => $index,
                    'alias' => $alias
                ]
            ];
        }

        if (!empty($actions)) {
            return $this->openSearchClient->indices()->updateAliases([
                'body' => [
                    'actions' => $actions
                ]
            ]);
        }

        return null;
    }

}