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

namespace Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch;

use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\SearchClient\SearchClientInterface;

/**
 * @internal
 */
final class IndexAliasService implements IndexAliasServiceInterface
{
    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly SearchIndexConfigServiceInterface $searchIndexConfigService,
    ) {
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

        return $this->client->updateIndexAliases($params);
    }

    public function existsAlias(string $aliasName, ?string $indexName = null): bool
    {
        return $this->client->existsIndexAlias([
            'name' => $aliasName,
            'index' => $indexName,
            'client' => [
                'ignore' => [404],
            ],
        ]);
    }

    public function getAllAliases(): array
    {
        return $this->client->getAllIndexAliases([
            'name' => $this->searchIndexConfigService->getIndexPrefix() . '*',
            'format' => 'json',
        ]);
    }

    public function deleteAlias(string $indexName, string $aliasName): array
    {
        return $this->client->deleteIndexAlias([
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
                    'alias' => $alias,
                ],
            ];
        }

        foreach ($toRemove as $index) {
            $actions[] = [
                'remove' => [
                    'index' => $index,
                    'alias' => $alias,
                ],
            ];
        }

        if (!empty($actions)) {
            return $this->client->updateIndexAliases([
                'body' => [
                    'actions' => $actions,
                ],
            ]);
        }

        return null;
    }
}
