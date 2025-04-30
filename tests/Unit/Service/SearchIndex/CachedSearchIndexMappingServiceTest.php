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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CachedSearchIndexMappingService;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolver;

/**
 * @internal
 */
final class CachedSearchIndexMappingServiceTest extends Unit
{
    private CachedSearchIndexMappingService $cachedSearchIndexMappingService;

    public function _before(): void
    {
        $searchIndexServiceMock = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexServiceMock->method('getMapping')->willReturnCallback(function (string $indexName) {
            return [$indexName . 'df' . uniqid('', true)];
        });

        $cacheResolver = new RuntimeCacheResolver();
        $cacheResolver->clear();

        $this->cachedSearchIndexMappingService = new CachedSearchIndexMappingService(
            $cacheResolver,
            $searchIndexServiceMock
        );
    }

    public function testStartStopCaching(): void
    {
        $this->assertFalse($this->cachedSearchIndexMappingService->isCachingStarted());
        $this->cachedSearchIndexMappingService->startCaching();
        $this->assertTrue($this->cachedSearchIndexMappingService->isCachingStarted());
        $this->cachedSearchIndexMappingService->stopCaching();
        $this->assertFalse($this->cachedSearchIndexMappingService->isCachingStarted());
    }

    public function testGetMapping(): void
    {
        $this->cachedSearchIndexMappingService->startCaching();
        $mapping = $this->cachedSearchIndexMappingService->getMapping('test');
        $this->assertSame($mapping, $this->cachedSearchIndexMappingService->getMapping('test'));
        $this->assertNotSame($mapping, $this->cachedSearchIndexMappingService->getMapping('testing'));

        $this->cachedSearchIndexMappingService->stopCaching();
        $this->assertNotSame(
            $this->cachedSearchIndexMappingService->getMapping('test'),
            $this->cachedSearchIndexMappingService->getMapping('test')
        );
        $this->assertNotSame(
            $this->cachedSearchIndexMappingService->getMapping('test'),
            $this->cachedSearchIndexMappingService->getMapping('test')
        );

        $this->cachedSearchIndexMappingService->startCaching();
        $this->assertSame(
            $this->cachedSearchIndexMappingService->getMapping('test'),
            $this->cachedSearchIndexMappingService->getMapping('test')
        );
        $this->assertSame(
            $this->cachedSearchIndexMappingService->getMapping('test'),
            $this->cachedSearchIndexMappingService->getMapping('test')
        );
    }
}
