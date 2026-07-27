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

use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\DefaultSearch\DefaultSearchService;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;

/**
 * @internal
 */
final readonly class UnusedIndexCleanupService
{
    public const DEFAULT_MIN_AGE_SECONDS = 86400;

    private const INDEX_SUFFIX_PATTERN = '/-('
        . DefaultSearchService::INDEX_VERSION_ODD
        . '|'
        . DefaultSearchService::INDEX_VERSION_EVEN
        . ')$/';

    public function __construct(
        private SearchIndexServiceInterface $searchIndexService,
        private IndexAliasServiceInterface $indexAliasService,
        private SearchIndexConfigServiceInterface $searchIndexConfigService,
    ) {
    }

    /**
     * @return string[]
     */
    public function findUnusedIndices(int $minAgeSeconds = self::DEFAULT_MIN_AGE_SECONDS): array
    {
        $allManagedIndices = $this->getAllManagedIndices($minAgeSeconds);
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
    public function cleanupUnusedIndices(
        bool $dryRun = false,
        int $minAgeSeconds = self::DEFAULT_MIN_AGE_SECONDS
    ): array {
        $unusedIndices = $this->findUnusedIndices($minAgeSeconds);

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
    private function getAllManagedIndices(int $minAgeSeconds): array
    {
        $indexPrefix = $this->searchIndexConfigService->getIndexPrefix();
        if ($indexPrefix === '') {
            return [];
        }

        $settingsByIndex = $this->searchIndexService->getIndexSettings($indexPrefix . '*');

        $indexNames = [];
        foreach ($settingsByIndex as $indexName => $indexSettings) {
            if (!is_string($indexName)
                || !str_starts_with($indexName, $indexPrefix)
                || preg_match(self::INDEX_SUFFIX_PATTERN, $indexName) !== 1
                || !$this->isOldEnough($indexSettings, $minAgeSeconds)
            ) {
                continue;
            }

            $indexNames[] = $indexName;
        }

        return $indexNames;
    }

    /**
     * A reindex creates and populates the new -odd/-even index before attaching it to its
     * alias, so a recently created index without an alias may still be in that window.
     * Indices with an unknown creation date never qualify for deletion unless the guard
     * is disabled ($minAgeSeconds <= 0).
     */
    private function isOldEnough(mixed $indexSettings, int $minAgeSeconds): bool
    {
        if ($minAgeSeconds <= 0) {
            return true;
        }

        $creationDate = $indexSettings['settings']['index']['creation_date'] ?? null;
        if (!is_numeric($creationDate)) {
            return false;
        }

        $creationTimestamp = (int) ((float) $creationDate / 1000);

        return time() - $creationTimestamp >= $minAgeSeconds;
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
