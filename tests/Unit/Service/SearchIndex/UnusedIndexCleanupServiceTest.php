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
            ->method('getIndexSettings')
            ->with('pimcore_*')
            ->willReturn([
                'pimcore_asset-odd' => $this->indexSettings('-2 days'),
                'pimcore_asset-even' => $this->indexSettings('-2 days'),
                'pimcore_document-even' => $this->indexSettings('-2 days'),
                'pimcore_custom' => $this->indexSettings('-2 days'),
                'other_prefix_asset-odd' => $this->indexSettings('-2 days'),
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

        $service = new UnusedIndexCleanupService(
            $searchIndexService,
            $indexAliasService,
            $this->mockConfigService('pimcore_')
        );

        $this->assertSame(['pimcore_asset-odd'], $service->findUnusedIndices());
    }

    public function testRecentlyCreatedIndicesAreProtectedByMinAge(): void
    {
        $searchIndexService = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexService
            ->method('getIndexSettings')
            ->willReturn([
                'pimcore_asset-odd' => $this->indexSettings('-10 minutes'),
                'pimcore_asset-even' => $this->indexSettings('-2 days'),
                'pimcore_document-odd' => [], // creation date unknown
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

        $service = new UnusedIndexCleanupService(
            $searchIndexService,
            $indexAliasService,
            $this->mockConfigService('pimcore_')
        );

        // The fresh index and the index with unknown creation date are not considered unused.
        $this->assertSame([], $service->findUnusedIndices());

        // Disabling the age guard includes both.
        $this->assertSame(
            ['pimcore_asset-odd', 'pimcore_document-odd'],
            $service->findUnusedIndices(0)
        );
    }

    public function testDryRunDoesNotDeleteIndices(): void
    {
        $searchIndexService = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexService
            ->method('getIndexSettings')
            ->willReturn([
                'pimcore_asset-odd' => $this->indexSettings('-2 days'),
                'pimcore_asset-even' => $this->indexSettings('-2 days'),
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

        $service = new UnusedIndexCleanupService(
            $searchIndexService,
            $indexAliasService,
            $this->mockConfigService('pimcore_')
        );

        $this->assertSame(['pimcore_asset-odd'], $service->cleanupUnusedIndices(true));
    }

    public function testExecuteDeletesUnusedIndices(): void
    {
        $searchIndexService = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexService
            ->method('getIndexSettings')
            ->willReturn([
                'pimcore_asset-odd' => $this->indexSettings('-2 days'),
                'pimcore_asset-even' => $this->indexSettings('-2 days'),
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

        $service = new UnusedIndexCleanupService(
            $searchIndexService,
            $indexAliasService,
            $this->mockConfigService('pimcore_')
        );

        $this->assertSame(['pimcore_asset-odd'], $service->cleanupUnusedIndices());
    }

    public function testGetIndexSettingsExceptionIsNotSwallowed(): void
    {
        $searchIndexService = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexService
            ->method('getIndexSettings')
            ->willThrowException(new RuntimeException('search engine unavailable'))
        ;
        $searchIndexService->expects($this->never())->method('deleteIndex');

        $indexAliasService = $this->createMock(IndexAliasServiceInterface::class);

        $service = new UnusedIndexCleanupService(
            $searchIndexService,
            $indexAliasService,
            $this->mockConfigService('pimcore_')
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('search engine unavailable');

        $service->cleanupUnusedIndices();
    }

    public function testEmptyPrefixReturnsNoIndices(): void
    {
        $searchIndexService = $this->createMock(SearchIndexServiceInterface::class);
        $searchIndexService->expects($this->never())->method('getIndexSettings');
        $searchIndexService->expects($this->never())->method('deleteIndex');

        $indexAliasService = $this->createMock(IndexAliasServiceInterface::class);

        $service = new UnusedIndexCleanupService(
            $searchIndexService,
            $indexAliasService,
            $this->mockConfigService('')
        );

        $this->assertSame([], $service->findUnusedIndices());
    }

    private function mockConfigService(string $indexPrefix): SearchIndexConfigServiceInterface
    {
        $searchIndexConfigService = $this->createMock(SearchIndexConfigServiceInterface::class);
        $searchIndexConfigService
            ->method('getIndexPrefix')
            ->willReturn($indexPrefix)
        ;

        return $searchIndexConfigService;
    }

    private function indexSettings(string $createdAt): array
    {
        return [
            'settings' => [
                'index' => [
                    'creation_date' => (string) (strtotime($createdAt) * 1000),
                ],
            ],
        ];
    }
}
