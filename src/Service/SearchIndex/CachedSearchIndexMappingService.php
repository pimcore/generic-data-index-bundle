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

use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;

/**
 * @internal
 */
final readonly class CachedSearchIndexMappingService implements CachedSearchIndexMappingServiceInterface
{
    private const CACHE_KEY = 'gdi_cached_search_index_mapping';

    public function __construct(
        private RuntimeCacheResolverInterface $runtimeCache,
        private SearchIndexServiceInterface $searchIndexService,
    ) {
    }

    public function startCaching(): void
    {
        if ($this->isCachingStarted()) {
            return;
        }
        $this->runtimeCache->save([], self::CACHE_KEY);
    }

    public function stopCaching(): void
    {
        $this->runtimeCache->save(null, self::CACHE_KEY);
    }

    public function getMapping(string $indexName): array
    {
        if ($this->isCachingStarted()) {
            $cachedMappings = $this->getCachedMappings();
            $cachedMappings[$indexName] ??= $this->searchIndexService->getMapping($indexName);
            $this->writeCachedMappings($cachedMappings);

            return $cachedMappings[$indexName];
        }

        return $this->searchIndexService->getMapping($indexName);
    }

    public function isCachingStarted(): bool
    {
        return is_array($this->getCachedMappings());
    }

    public function getCachedMappings(): ?array
    {
        $cachedMappings = $this->runtimeCache->isRegistered(self::CACHE_KEY)
            ? $this->runtimeCache->load(self::CACHE_KEY) : null;

        return is_array($cachedMappings) ? $cachedMappings : null;
    }

    private function writeCachedMappings(array $mappings): void
    {
        $this->runtimeCache->save($mappings, self::CACHE_KEY);
    }
}
