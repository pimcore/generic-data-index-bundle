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
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CachedSearchIndexMappingService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessageService;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolver;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Cache\RuntimeCache;
use Pimcore\Model\DataObject\Fieldcollection;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @internal
 */
final class CachedSearchIndexMappingServiceTest extends Unit
{
    private CachedSearchIndexMappingService $cachedSearchIndexMappingService;

    public function _before(): void
    {
        $runtimeCacheResolver = new class implements RuntimeCacheResolverInterface {
            private ?array $cacheEntry = null;
            public function load(string $id): mixed
            {
                return $this->cacheEntry;
            }

            public function save(mixed $data, string $id): void
            {
                $this->cacheEntry = $data;
            }

            public function isRegistered(string $index): bool
            {
                return is_array($this->cacheEntry);
            }

            public function clear(array $keepItems = []): void
            {
                $this->cacheEntry = null;
            }
        };


        $searchIndexServiceMock = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexServiceMock->method('getMapping')->willReturnCallback(function (string $indexName) {
            return [$indexName . 'df' . uniqid('', true)];
        });

        $this->cachedSearchIndexMappingService = new CachedSearchIndexMappingService(
            $runtimeCacheResolver,
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
