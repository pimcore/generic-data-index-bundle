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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CachedSearchIndexMappingService;
use Pimcore\Bundle\StaticResolverBundle\Contract\Lib\Cache\RuntimeCacheResolverContract;

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

        $this->cachedSearchIndexMappingService = new CachedSearchIndexMappingService(
            new RuntimeCacheResolverContract(),
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
