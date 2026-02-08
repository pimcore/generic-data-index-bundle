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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;

/**
 * @internal
 */
final readonly class UnusedIndexCleanupService
{
    private const INDEX_SUFFIX_PATTERN = '/-(odd|even)$/';

    public function __construct(
        private SearchIndexServiceInterface $searchIndexService,
        private IndexAliasServiceInterface $indexAliasService,
        private SearchIndexConfigServiceInterface $searchIndexConfigService,
    ) {
    }

    /**
     * @return string[]
     */
    public function findUnusedIndices(): array
    {
        $allManagedIndices = $this->getAllManagedIndices();
        if (empty($allManagedIndices)) {
            return [];
        }

        $aliasedIndices = $this->getAliasedIndices();
        $unusedIndices = array_values(array_diff($allManagedIndices, $aliasedIndices));
        sort($unusedIndices);

        return $unusedIndices;
    }

    /**
     * @return string[]
     */
    public function cleanupUnusedIndices(bool $dryRun = false): array
    {
        $unusedIndices = $this->findUnusedIndices();

        if ($dryRun) {
            return $unusedIndices;
        }

        foreach ($unusedIndices as $indexName) {
            $this->searchIndexService->deleteIndex($indexName);
        }

        return $unusedIndices;
    }

    /**
     * @return string[]
     */
    private function getAllManagedIndices(): array
    {
        $indexPrefix = $this->searchIndexConfigService->getIndexPrefix();

        try {
            $stats = $this->searchIndexService->getStats($indexPrefix . '*');
        } catch (Exception) {
            return [];
        }

        $indices = $stats['indices'] ?? null;
        if (!is_array($indices)) {
            return [];
        }

        $indexNames = array_keys($indices);

        return array_values(array_filter(
            $indexNames,
            static fn (string $indexName): bool => str_starts_with($indexName, $indexPrefix)
                && preg_match(self::INDEX_SUFFIX_PATTERN, $indexName) === 1
        ));
    }

    /**
     * @return string[]
     */
    private function getAliasedIndices(): array
    {
        $indexPrefix = $this->searchIndexConfigService->getIndexPrefix();
        $aliases = $this->indexAliasService->getAllAliases();

        $aliasedIndexMap = [];
        foreach ($aliases as $aliasData) {
            if (!is_array($aliasData)) {
                continue;
            }

            $indexName = $aliasData['index'] ?? null;
            if (!is_string($indexName) || !str_starts_with($indexName, $indexPrefix)) {
                continue;
            }

            $aliasedIndexMap[$indexName] = true;
        }

        return array_keys($aliasedIndexMap);
    }
}
