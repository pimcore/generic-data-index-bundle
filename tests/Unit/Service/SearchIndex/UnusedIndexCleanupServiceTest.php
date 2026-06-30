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
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\IndexAliasServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\SearchIndexConfigServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\UnusedIndexCleanupService;
use RuntimeException;

/**
 * @internal
 */
final class UnusedIndexCleanupServiceTest extends Unit
{
    public function testFindUnusedIndicesReturnsOnlyUnaliasedManagedIndices(): void
    {
        $searchIndexService = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexService
            ->method('getStats')
            ->with('pimcore_*')
            ->willReturn([
                'indices' => [
                    'pimcore_asset-odd' => [],
                    'pimcore_asset-even' => [],
                    'pimcore_document-even' => [],
                    'pimcore_custom' => [],
                    'other_prefix_asset-odd' => [],
                ],
            ])
        ;
        $searchIndexService->expects($this->never())->method('deleteIndex');

        $indexAliasService = $this->createMock(IndexAliasServiceInterface::class);
        $indexAliasService
            ->method('getAllAliases')
            ->willReturn([
                ['alias' => 'pimcore_asset', 'index' => 'pimcore_asset-even'],
                ['alias' => 'pimcore_document', 'index' => 'pimcore_document-even'],
            ])
        ;

        $searchIndexConfigService = $this->createMock(SearchIndexConfigServiceInterface::class);
        $searchIndexConfigService
            ->method('getIndexPrefix')
            ->willReturn('pimcore_')
        ;

        $service = new UnusedIndexCleanupService(
            $searchIndexService,
            $indexAliasService,
            $searchIndexConfigService
        );

        $this->assertSame(['pimcore_asset-odd'], $service->findUnusedIndices());
    }

    public function testDryRunDoesNotDeleteIndices(): void
    {
        $searchIndexService = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexService
            ->method('getStats')
            ->willReturn([
                'indices' => [
                    'pimcore_asset-odd' => [],
                    'pimcore_asset-even' => [],
                ],
            ])
        ;
        $searchIndexService->expects($this->never())->method('deleteIndex');

        $indexAliasService = $this->createMock(IndexAliasServiceInterface::class);
        $indexAliasService
            ->method('getAllAliases')
            ->willReturn([
                ['alias' => 'pimcore_asset', 'index' => 'pimcore_asset-even'],
            ])
        ;

        $searchIndexConfigService = $this->createMock(SearchIndexConfigServiceInterface::class);
        $searchIndexConfigService
            ->method('getIndexPrefix')
            ->willReturn('pimcore_')
        ;

        $service = new UnusedIndexCleanupService(
            $searchIndexService,
            $indexAliasService,
            $searchIndexConfigService
        );

        $this->assertSame(['pimcore_asset-odd'], $service->cleanupUnusedIndices(true));
    }

    public function testExecuteDeletesUnusedIndices(): void
    {
        $searchIndexService = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexService
            ->method('getStats')
            ->willReturn([
                'indices' => [
                    'pimcore_asset-odd' => [],
                    'pimcore_asset-even' => [],
                ],
            ])
        ;
        $searchIndexService
            ->expects($this->once())
            ->method('deleteIndex')
            ->with('pimcore_asset-odd')
        ;

        $indexAliasService = $this->createMock(IndexAliasServiceInterface::class);
        $indexAliasService
            ->method('getAllAliases')
            ->willReturn([
                ['alias' => 'pimcore_asset', 'index' => 'pimcore_asset-even'],
            ])
        ;

        $searchIndexConfigService = $this->createMock(SearchIndexConfigServiceInterface::class);
        $searchIndexConfigService
            ->method('getIndexPrefix')
            ->willReturn('pimcore_')
        ;

        $service = new UnusedIndexCleanupService(
            $searchIndexService,
            $indexAliasService,
            $searchIndexConfigService
        );

        $this->assertSame(['pimcore_asset-odd'], $service->cleanupUnusedIndices());
    }

    public function testGetStatsExceptionIsNotSwallowed(): void
    {
        $searchIndexService = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexService
            ->method('getStats')
            ->willThrowException(new RuntimeException('search engine unavailable'))
        ;
        $searchIndexService->expects($this->never())->method('deleteIndex');

        $indexAliasService = $this->createMock(IndexAliasServiceInterface::class);

        $searchIndexConfigService = $this->createMock(SearchIndexConfigServiceInterface::class);
        $searchIndexConfigService
            ->method('getIndexPrefix')
            ->willReturn('pimcore_')
        ;

        $service = new UnusedIndexCleanupService(
            $searchIndexService,
            $indexAliasService,
            $searchIndexConfigService
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('search engine unavailable');

        $service->cleanupUnusedIndices();
    }

    public function testEmptyPrefixReturnsNoIndices(): void
    {
        $searchIndexService = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexService->expects($this->never())->method('getStats');
        $searchIndexService->expects($this->never())->method('deleteIndex');

        $indexAliasService = $this->createMock(IndexAliasServiceInterface::class);

        $searchIndexConfigService = $this->createMock(SearchIndexConfigServiceInterface::class);
        $searchIndexConfigService
            ->method('getIndexPrefix')
            ->willReturn('')
        ;

        $service = new UnusedIndexCleanupService(
            $searchIndexService,
            $indexAliasService,
            $searchIndexConfigService
        );

        $this->assertSame([], $service->findUnusedIndices());
    }
}
