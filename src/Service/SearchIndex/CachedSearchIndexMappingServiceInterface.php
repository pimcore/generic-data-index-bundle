<?php
declare(strict_types=1);

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex;

/**
 * @internal
 */
interface CachedSearchIndexMappingServiceInterface
{
    public function startCaching(): void;
    public function stopCaching(): void;
    public function isCachingStarted(): bool;
    public function getMapping(string $indexName): array;
}