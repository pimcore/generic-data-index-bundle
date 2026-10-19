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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Functional\SearchIndex;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexStatusState;
use Pimcore\Bundle\GenericDataIndexBundle\Event\IndexStatus\IndexCompleteEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexStatusException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexStatus;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\EnqueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AssetTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\DataObjectIndexHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\IndexHandler\IndexHandlerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexStatusServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SettingsStoreServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Tests\IndexTester;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Tests\Support\Util\TestHelper;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The asset index through a full rebuild: rebuilding with progress, complete with the event, degraded after the
 * checksums are gone.
 *
 * @internal
 */
final class IndexStatusTest extends Unit
{
    protected IndexTester $tester;

    private IndexStatusServiceInterface $statusService;

    private string $assetAlias;

    protected function _before(): void
    {
        $this->statusService = $this->tester->grabService(IndexStatusServiceInterface::class);
        $this->assetAlias = $this->tester->grabService(AssetTypeAdapter::class)->getAliasIndexName();
        $this->tester->disableSynchronousProcessing();
        $this->tester->clearQueue();
    }

    protected function _after(): void
    {
        TestHelper::cleanUp();
        $this->tester->flushIndex();
        $this->tester->cleanupIndex();
        $this->tester->flushIndex();
    }

    public function testAssetIndexThroughAFullRebuild(): void
    {
        TestHelper::createImageAsset('status-');
        TestHelper::createImageAsset('status-');
        $this->tester->clearQueue();

        /** @var EnqueueServiceInterface $enqueueService */
        $enqueueService = $this->tester->grabService(EnqueueServiceInterface::class);
        $enqueueService->enqueueAssets();

        $rebuilding = $this->statusService->getAssetStatus();
        $this->assertSame(IndexStatusState::REBUILDING, $rebuilding->getState());
        $this->assertGreaterThanOrEqual(2, $rebuilding->getElementCount());
        $this->assertSame($rebuilding->getElementCount(), $rebuilding->getPendingEntries());
        $this->assertSame(0.0, $rebuilding->getPercentage());
        $this->assertNotNull($rebuilding->getRebuildQueuedAt());
        $this->assertSame($this->assetAlias, $rebuilding->getIndexName());

        $events = [];
        $this->tester->grabService(EventDispatcherInterface::class)->addListener(
            IndexCompleteEvent::class,
            static function (IndexCompleteEvent $event) use (&$events): void {
                $events[] = $event;
            }
        );
        $this->processQueue();

        $complete = $this->statusService->getAssetStatus();
        $this->assertSame(IndexStatusState::COMPLETE, $complete->getState());
        $this->assertSame($complete->getElementCount(), $complete->getDocumentCount());
        $this->assertSame(0, $complete->getPendingEntries());
        $this->assertNull($complete->getRebuildQueuedAt(), 'The marker is cleared with the last batch');
        $this->assertCount(1, $events, 'The event fires once, with the last batch');
        $this->assertSame($this->assetAlias, $events[0]->getIndexName());

        // the same index by its names
        $this->assertSame($this->assetAlias, $this->statusService->getStatusByIndexName('asset')->getIndexName());
        $this->assertSame(
            $this->assetAlias,
            $this->statusService->getStatusByIndexName($this->assetAlias)->getIndexName()
        );
        $assetInReport = array_values(array_filter(
            $this->statusService->getAll()->getIndices(),
            fn (IndexStatus $status): bool => $status->getIndexName() === $this->assetAlias
        ));
        $this->assertCount(1, $assetInReport);
        $this->assertTrue($assetInReport[0]->isComplete());

        // a copy that left out fields removes the checksums; until the rebuild the index is degraded
        $this->tester->getIndexSearchClient()->updateByQuery([
            'index' => $this->assetAlias,
            'refresh' => true,
            'body' => [
                'script' => ['source' => "ctx._source.system_fields.remove('checksum')", 'lang' => 'painless'],
                'query' => ['match_all' => (object) []],
            ],
        ]);

        $degraded = $this->statusService->getAssetStatus();
        $this->assertSame(IndexStatusState::DEGRADED, $degraded->getState());
        $this->assertSame($degraded->getDocumentCount(), $degraded->getDocumentsWithoutChecksum());
        $this->assertSame(0.0, $degraded->getPercentage());
    }

    /**
     * No worker will ever process a batch of a class without objects: the enqueue itself reports the rebuild.
     */
    public function testARebuildOfAnIndexWithoutElementsIsCompleteAtOnce(): void
    {
        $classDefinition = ClassDefinition::getByName('mappingTest');
        $this->assertNotNull($classDefinition);
        $this->assertSame(0, $this->statusService->getStatusForClassDefinition($classDefinition)->getElementCount());

        $events = [];
        $this->tester->grabService(EventDispatcherInterface::class)->addListener(
            IndexCompleteEvent::class,
            static function (IndexCompleteEvent $event) use (&$events): void {
                $events[] = $event;
            }
        );
        $this->tester->grabService(EnqueueServiceInterface::class)->enqueueByClassDefinition($classDefinition);

        $status = $this->statusService->getStatusForClassDefinition($classDefinition);
        $this->assertSame(IndexStatusState::COMPLETE, $status->getState());
        $this->assertNull($status->getRebuildQueuedAt(), 'No marker is left behind for the next unrelated batch');
        $this->assertCount(1, $events);
        $this->assertSame($status->getIndexName(), $events[0]->getIndexName());
    }

    /**
     * A class checksum stored by an older bundle version (depending on the key order) still counts as current.
     */
    public function testTheClassMappingIsCurrentWithTheStoredOrTheLegacyChecksum(): void
    {
        $classDefinition = ClassDefinition::getByName('unittest');
        $this->assertNotNull($classDefinition);
        /** @var SettingsStoreServiceInterface $settingsStore */
        $settingsStore = $this->tester->grabService(SettingsStoreServiceInterface::class);
        /** @var IndexHandlerInterface $handler */
        $handler = $this->tester->grabService(DataObjectIndexHandler::class);
        $mappingProperties = $handler->getMappingProperties($classDefinition);
        $current = $settingsStore->getClassMappingCheckSum($classDefinition->getId());
        $this->assertSame($handler->getClassMappingCheckSum($mappingProperties), $current);

        try {
            $this->assertTrue($this->statusService->getStatusForClassDefinition($classDefinition)->isMappingCurrent());

            $settingsStore->storeClassMapping($classDefinition->getId(), crc32(json_encode($mappingProperties)));
            $this->assertTrue(
                $this->statusService->getStatusForClassDefinition($classDefinition)->isMappingCurrent(),
                'The legacy checksum of an unchanged mapping is current'
            );

            $settingsStore->storeClassMapping($classDefinition->getId(), 123);
            $this->assertFalse($this->statusService->getStatusForClassDefinition($classDefinition)->isMappingCurrent());
        } finally {
            $settingsStore->storeClassMapping($classDefinition->getId(), (int) $current);
        }
    }

    public function testAnUnknownIndexNameIsRejected(): void
    {
        $this->expectException(IndexStatusException::class);
        $this->statusService->getStatusByIndexName('no-such-index');
    }

    public function testTheStatusCommandReportsEveryIndex(): void
    {
        $output = $this->tester->runConsoleCommand('generic-data-index:status', ['--json' => true]);
        $json = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('indices', $json);
        $names = array_column($json['indices'], 'index');
        $this->assertContains($this->assetAlias, $names);

        $output = $this->tester->runConsoleCommand('generic-data-index:status', ['--index' => 'asset']);
        $this->assertStringContainsString($this->assetAlias, $output);
    }

    /**
     * Processes the index queue in this process, so the completion event reaches the listener above.
     */
    private function processQueue(): void
    {
        /** @var IndexQueueRepository $repository */
        $repository = $this->tester->grabService(IndexQueueRepository::class);
        /** @var IndexQueueServiceInterface $queueService */
        $queueService = $this->tester->grabService(IndexQueueServiceInterface::class);
        for ($i = 0; $i < 20 && $repository->countIndexQueueEntries() > 0; $i++) {
            $entries = array_map(
                static fn (array $entry) => $repository->denormalizeDatabaseEntry($entry),
                $repository->getUnhandledIndexQueueEntries(true, 2)
            );
            $queueService->handleIndexQueueEntries($entries);
        }
        $this->tester->flushIndex();
    }
}
