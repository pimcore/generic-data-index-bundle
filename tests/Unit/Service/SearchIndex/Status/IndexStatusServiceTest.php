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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex\Status;

use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexStatusState;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\DocumentCountFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\DefaultSearch\ReindexFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexStatusException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexState;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexRebuildMarker;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexCountRepositoryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\SearchIndexAdapter\SearchIndexServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Deployment\IndexStateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexRebuildStateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexStateTargetResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexStatusService;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

/**
 * The state is derived from the counts; every rule gets the one situation it decides.
 *
 * @internal
 */
final class IndexStatusServiceTest extends Unit
{
    private const ALIAS = 'pimcore_asset';

    public function testAMissingIndexIsReportedWithoutEngineCounts(): void
    {
        $status = $this->createService(exists: false, elements: 10)->getAssetStatus();

        $this->assertSame(IndexStatusState::MISSING, $status->getState());
        $this->assertSame(0, $status->getDocumentCount());
        $this->assertSame(10, $status->getElementCount());
        $this->assertNull($status->isMappingCurrent());
    }

    public function testARunningCopyWinsOverEverythingElse(): void
    {
        $status = $this->createService(
            documents: 10,
            elements: 100,
            pending: 90,
            queuedAt: 1,
            tasks: [['id' => 'n:1', 'source' => self::ALIAS . '-odd', 'target' => self::ALIAS . '-even', 'created' => 5, 'total' => 10]]
        )->getAssetStatus();

        $this->assertSame(IndexStatusState::COPYING, $status->getState());
        $this->assertTrue($status->isCopyRunning());
    }

    public function testACopyOfAnotherIndexDoesNotCount(): void
    {
        $status = $this->createService(
            documents: 10,
            elements: 10,
            tasks: [['id' => 'n:1', 'source' => 'pimcore_document-odd', 'target' => 'pimcore_document-even', 'created' => 0, 'total' => 0]]
        )->getAssetStatus();

        $this->assertSame(IndexStatusState::COMPLETE, $status->getState());
        $this->assertFalse($status->isCopyRunning());
    }

    public function testDocumentsWithoutChecksumMeanDegraded(): void
    {
        $status = $this->createService(documents: 10, elements: 10, withoutChecksum: 4)->getAssetStatus();

        $this->assertSame(IndexStatusState::DEGRADED, $status->getState());
        $this->assertSame(60.0, $status->getPercentage());
    }

    public function testAQueuedRebuildWithPendingEntriesIsRebuilding(): void
    {
        $status = $this->createService(documents: 100, elements: 100, pending: 30, queuedAt: 1700000000000)
            ->getAssetStatus();

        $this->assertSame(IndexStatusState::REBUILDING, $status->getState());
        $this->assertSame(70.0, $status->getPercentage());
        $this->assertSame(1700000000000, $status->getRebuildQueuedAt());
    }

    public function testARecreatedIndexWithAQueuedRebuildIsRebuilding(): void
    {
        $status = $this->createService(documents: 40, elements: 100, pending: 60, queuedAt: 1)->getAssetStatus();

        $this->assertSame(IndexStatusState::REBUILDING, $status->getState());
        $this->assertSame(40.0, $status->getPercentage());
    }

    /**
     * The first moments of a rebuild: the marker is set, the entries are still being written.
     */
    public function testARebuildWhoseElementsAreStillBeingQueuedIsRebuildingAtZeroPercent(): void
    {
        $status = $this->createService(documents: 100, elements: 100, pending: 0, queuedAt: 1, enqueued: false)
            ->getAssetStatus();

        $this->assertSame(IndexStatusState::REBUILDING, $status->getState());
        $this->assertTrue($status->isRebuildEnqueuing());
        $this->assertSame(0.0, $status->getPercentage());

        $halfQueued = $this->createService(documents: 100, elements: 100, pending: 50, queuedAt: 1, enqueued: false)
            ->getAssetStatus();
        $this->assertSame(0.0, $halfQueued->getPercentage(), 'The pending entries are not the full size yet');
    }

    public function testMissingDocumentsWithoutAnythingQueuedAreIncomplete(): void
    {
        $status = $this->createService(documents: 90, elements: 100)->getAssetStatus();

        $this->assertSame(IndexStatusState::INCOMPLETE, $status->getState());
        $this->assertNull($status->getPercentage());
    }

    public function testNormalLagIsComplete(): void
    {
        $status = $this->createService(documents: 98, elements: 100, pending: 2)->getAssetStatus();

        $this->assertSame(IndexStatusState::COMPLETE, $status->getState());
        $this->assertSame(2, $status->getPendingEntries());
    }

    /**
     * A worker died after removing its entries and before reporting: the marker is left, no work remains. The
     * counts decide, so missing documents ask for a rebuild instead of showing a rebuild at 100 % forever.
     */
    public function testAStaleRebuildMarkerDoesNotReportARebuild(): void
    {
        $complete = $this->createService(documents: 100, elements: 100, queuedAt: 1)->getAssetStatus();
        $this->assertSame(IndexStatusState::COMPLETE, $complete->getState());

        $incomplete = $this->createService(documents: 90, elements: 100, queuedAt: 1)->getAssetStatus();
        $this->assertSame(IndexStatusState::INCOMPLETE, $incomplete->getState());
        $this->assertNull($incomplete->getPercentage());
        $this->assertSame(1, $incomplete->getRebuildQueuedAt(), 'The marker is still reported as a fact');
    }

    public function testMappingAndSettingsAreComparedWithTheStoredState(): void
    {
        $current = $this->createService(documents: 1, elements: 1)->getAssetStatus();
        $this->assertTrue($current->isMappingCurrent());
        $this->assertTrue($current->isSettingsCurrent());

        $outdated = $this->createService(
            documents: 1,
            elements: 1,
            stored: new IndexState('old', 'old', '1.0'),
            mappingCurrent: false
        )->getAssetStatus();
        $this->assertFalse($outdated->isMappingCurrent());
        $this->assertFalse($outdated->isSettingsCurrent());

        $unknown = $this->createService(
            documents: 1,
            elements: 1,
            stored: new IndexState(null, null, null),
            mappingCurrent: null
        )->getAssetStatus();
        $this->assertNull($unknown->isMappingCurrent());
        $this->assertNull($unknown->isSettingsCurrent());
    }

    public function testAClassIndexIsLabelledByItsClass(): void
    {
        $classDefinition = new ClassDefinition();
        $classDefinition->setId('CAR');
        $classDefinition->setName('Car');

        $status = $this->createService(documents: 1, elements: 1, mappingCurrent: false)
            ->getStatusForClassDefinition($classDefinition);

        $this->assertFalse($status->isMappingCurrent());
        $this->assertSame('class Car (CAR)', $status->getLabel());
        $this->assertSame('pimcore_data-object_car', $status->getIndexName());
    }

    /**
     * An unknown copy state must not pass as "no copy running": --wait and --check would release too early.
     */
    public function testAFailingTaskListingFailsTheStatusInsteadOfReportingComplete(): void
    {
        $service = $this->createService(documents: 10, elements: 10, tasksException: new ReindexFailedException('tasks API down'));

        $this->expectException(IndexStatusException::class);
        $this->expectExceptionMessage('tasks API down');
        $service->getAssetStatus();
    }

    /**
     * A partial count (failed shards) must not pass as a fact: a degraded index would read as complete.
     */
    public function testAPartialDocumentCountFailsTheStatusInsteadOfReportingComplete(): void
    {
        $service = $this->createService(
            documents: 10,
            elements: 10,
            countException: new DocumentCountFailedException('1 shard(s) failed')
        );

        $this->expectException(IndexStatusException::class);
        $this->expectExceptionMessage('1 shard(s) failed');
        $service->getAssetStatus();
    }

    public function testAnUnknownIndexNameIsRejected(): void
    {
        $this->expectException(IndexStatusException::class);
        $this->createService(documents: 1, elements: 1)->getStatusByIndexName('nope');
    }

    public function testWaitingReportsTheStateWhenTheTimeoutIsReached(): void
    {
        $this->expectException(IndexStatusException::class);
        $this->expectExceptionMessage('still incomplete after 0 seconds');
        $this->createService(documents: 90, elements: 100)->waitUntilComplete(self::ALIAS, 0);
    }

    public function testWaitingReturnsACompleteIndex(): void
    {
        $status = $this->createService(documents: 10, elements: 10)->waitUntilComplete(self::ALIAS, 0);

        $this->assertTrue($status->isComplete());
    }

    public function testTheReportCarriesTheQueueAndFailedMessageCounts(): void
    {
        $report = $this->createService(documents: 10, elements: 10, queueTotal: 7, failed: 2)->getAll();

        $this->assertTrue($report->isComplete());
        $this->assertSame(7, $report->getQueueEntries());
        $this->assertSame(2, $report->getFailedMessages());
        $this->assertCount(1, $report->getIndices());
    }

    /**
     * @param array<int, array{id: string, source: string, target: string, created: int, total: int}> $tasks
     */
    private function createService(
        bool $exists = true,
        int $documents = 0,
        int $elements = 0,
        int $pending = 0,
        int $withoutChecksum = 0,
        ?int $queuedAt = null,
        bool $enqueued = true,
        array $tasks = [],
        ?ReindexFailedException $tasksException = null,
        ?DocumentCountFailedException $countException = null,
        ?IndexState $stored = null,
        ?bool $mappingCurrent = true,
        int $queueTotal = 0,
        ?int $failed = null,
    ): IndexStatusService {
        $target = IndexStateTarget::forScope(DeploymentReindexScope::ASSETS);
        $resolver = $this->makeEmpty(IndexStateTargetResolverInterface::class, [
            'all' => [$target],
            'aliasName' => static fn (IndexStateTarget $t): string => $t->isClassIndex()
                ? 'pimcore_data-object_' . strtolower((string) $t->getClassDefinition()?->getName())
                : self::ALIAS,
            'elementIndexName' => static fn (IndexStateTarget $t): string => $t->isClassIndex()
                ? (string) $t->getClassDefinition()?->getName()
                : 'asset',
            'fromIndexName' => static fn (string $name): ?IndexStateTarget => $name === self::ALIAS ? $target : null,
        ]);
        $current = new IndexState('mapping', 'settings', '1.0');
        $indexState = $this->makeEmpty(IndexStateServiceInterface::class, [
            'indexExists' => $exists,
            'getCurrentState' => $current,
            'getStoredState' => $stored ?? $current,
            'isMappingCurrent' => $mappingCurrent,
        ]);
        $searchIndexService = $this->makeEmpty(SearchIndexServiceInterface::class, [
            'countDocuments' => static function (string $index, array $query = []) use (
                $documents,
                $withoutChecksum,
                $countException
            ): int {
                if ($countException !== null) {
                    throw $countException;
                }

                return $query === [] ? $documents : $withoutChecksum;
            },
            'getReindexTasks' => static function () use ($tasks, $tasksException): array {
                if ($tasksException !== null) {
                    throw $tasksException;
                }

                return $tasks;
            },
        ]);
        $counts = $this->makeEmpty(IndexCountRepositoryInterface::class, [
            'countElements' => $elements,
            'countQueueEntries' => static fn (?string $name = null): int => $name === null ? $queueTotal : $pending,
        ]);
        $marker = $queuedAt === null ? null : new IndexRebuildMarker($queuedAt, $enqueued, 'token');

        return new IndexStatusService(
            $resolver,
            $indexState,
            $searchIndexService,
            $counts,
            $this->makeEmpty(IndexRebuildStateServiceInterface::class, ['getMarker' => $marker]),
            $failed === null ? null : $this->makeEmpty(MessageCountAwareInterface::class, ['getMessageCount' => $failed]),
        );
    }
}
