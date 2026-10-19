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
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexStatusState;
use Pimcore\Bundle\GenericDataIndexBundle\Event\IndexStatus\IndexCompleteEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexStatusException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexRebuildMarker;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Status\IndexStatus;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexCountRepositoryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexCompletionNotifier;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexRebuildStateServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexStateTargetResolverInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status\IndexStatusServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class IndexCompletionNotifierTest extends Unit
{
    /** @var IndexCompleteEvent[] */
    private array $events = [];

    /** @var string[] */
    private array $claimed = [];

    /** @var string[] */
    private array $warnings = [];

    /** @var string[] */
    private array $errors = [];

    private int $statusReads = 0;

    protected function _before(): void
    {
        $this->events = [];
        $this->claimed = [];
        $this->warnings = [];
        $this->errors = [];
        $this->statusReads = 0;
    }

    public function testTheEventFiresOnceTheQueuedRebuildIsProcessed(): void
    {
        $notifier = $this->createNotifier(marker: $this->marker(enqueued: true), pending: 0, state: IndexStatusState::COMPLETE);

        $notifier->afterBatch([$this->entry('asset'), $this->entry('asset')]);

        $this->assertCount(1, $this->events, 'One event per index, not per entry');
        $this->assertSame('pimcore_asset', $this->events[0]->getIndexName());
        $this->assertSame(['asset'], $this->claimed);
    }

    public function testTheEnqueueReportsARebuildTheWorkersFinishedBeforeItOrOneWithoutElements(): void
    {
        $notifier = $this->createNotifier(marker: $this->marker(enqueued: true), pending: 0, state: IndexStatusState::COMPLETE);

        $notifier->afterEnqueue(IndexStateTarget::forScope(DeploymentReindexScope::ASSETS));

        $this->assertCount(1, $this->events);
        $this->assertSame(['asset'], $this->claimed);
    }

    public function testNothingHappensWithoutAQueuedRebuild(): void
    {
        $notifier = $this->createNotifier(marker: null, pending: 0, state: IndexStatusState::COMPLETE);

        $notifier->afterBatch([$this->entry('asset')]);

        $this->assertSame([], $this->events);
        $this->assertSame([], $this->claimed);
        $this->assertSame(0, $this->statusReads);
    }

    public function testNothingHappensWhileEntriesAreStillPending(): void
    {
        $notifier = $this->createNotifier(marker: $this->marker(enqueued: true), pending: 5, state: IndexStatusState::REBUILDING);

        $notifier->afterBatch([$this->entry('asset')]);

        $this->assertSame([], $this->events);
        $this->assertSame([], $this->claimed);
        $this->assertSame(0, $this->statusReads);
    }

    /**
     * Workers can drain the queue between two chunks of a large enqueue; the empty queue is not the end then.
     */
    public function testNothingHappensWhileTheElementsAreStillBeingQueued(): void
    {
        $notifier = $this->createNotifier(marker: $this->marker(enqueued: false), pending: 0, state: IndexStatusState::COMPLETE);

        $notifier->afterBatch([$this->entry('asset')]);
        $notifier->afterEnqueue(IndexStateTarget::forScope(DeploymentReindexScope::ASSETS));

        $this->assertSame([], $this->events);
        $this->assertSame([], $this->claimed);
    }

    /**
     * Two workers finish their last batches at once: both see an empty queue, one claims the marker, one event.
     */
    public function testOnlyTheWorkerThatClaimsTheMarkerReports(): void
    {
        $notifier = $this->createNotifier(
            marker: $this->marker(enqueued: true),
            pending: 0,
            state: IndexStatusState::COMPLETE,
            claimSucceeds: false
        );

        $notifier->afterBatch([$this->entry('asset')]);

        $this->assertSame([], $this->events);
        $this->assertSame([], $this->warnings);
        $this->assertSame(1, $this->statusReads, 'The status is read before the claim, so a failed read keeps the marker');
    }

    public function testAProcessedRebuildThatLeftTheIndexIncompleteClaimsTheMarkerWithoutAnEvent(): void
    {
        $notifier = $this->createNotifier(marker: $this->marker(enqueued: true), pending: 0, state: IndexStatusState::INCOMPLETE);

        $notifier->afterBatch([$this->entry('asset')]);

        $this->assertSame([], $this->events);
        $this->assertSame(['asset'], $this->claimed, 'The marker must not keep reporting a rebuild that is over');
        $this->assertCount(1, $this->warnings);
    }

    /**
     * The engine fails at the last batch: the marker stays, the next batch (or enqueue) of the index checks again.
     */
    public function testAFailingStatusReadKeepsTheMarkerAndDoesNotFailTheBatch(): void
    {
        $notifier = $this->createNotifier(
            marker: $this->marker(enqueued: true),
            pending: 0,
            state: IndexStatusState::COMPLETE,
            statusException: new IndexStatusException('engine down')
        );

        $notifier->afterBatch([$this->entry('asset')]);
        $this->assertSame([], $this->events);
        $this->assertSame([], $this->claimed);
        $this->assertCount(1, $this->errors);

        $notifier = $this->createNotifier(marker: $this->marker(enqueued: true), pending: 0, state: IndexStatusState::COMPLETE);
        $notifier->afterBatch([$this->entry('asset')]);
        $this->assertCount(1, $this->events, 'Recovered with the next batch');
        $this->assertSame(['asset'], $this->claimed);
    }

    public function testEntriesOfUnknownIndicesAreSkipped(): void
    {
        $notifier = $this->createNotifier(marker: $this->marker(enqueued: true), pending: 0, state: IndexStatusState::COMPLETE);

        $notifier->afterBatch([$this->entry('DeletedClass')]);

        $this->assertSame([], $this->events);
    }

    private function entry(string $indexName): IndexQueue
    {
        return (new IndexQueue())->setElementId(1)->setElementType('asset')->setElementIndexName($indexName);
    }

    private function marker(bool $enqueued): IndexRebuildMarker
    {
        return new IndexRebuildMarker(1700000000000, $enqueued);
    }

    private function createNotifier(
        ?IndexRebuildMarker $marker,
        int $pending,
        IndexStatusState $state,
        bool $claimSucceeds = true,
        ?IndexStatusException $statusException = null,
    ): IndexCompletionNotifier {
        $target = IndexStateTarget::forScope(DeploymentReindexScope::ASSETS);
        $resolver = $this->makeEmpty(IndexStateTargetResolverInterface::class, [
            'fromElementIndexName' => static fn (string $name): ?IndexStateTarget => $name === 'asset' ? $target : null,
            'aliasName' => 'pimcore_asset',
            'elementIndexName' => 'asset',
        ]);
        $rebuildState = $this->makeEmpty(IndexRebuildStateServiceInterface::class, [
            'getMarker' => $marker,
            'claim' => function (IndexStateTarget $t, IndexRebuildMarker $m) use ($marker, $claimSucceeds): bool {
                $this->assertSame($marker, $m, 'Exactly the observed marker is claimed');
                if ($claimSucceeds) {
                    $this->claimed[] = $t->getKey();
                }

                return $claimSucceeds;
            },
        ]);
        $status = new IndexStatus('pimcore_asset', 'assets', $state, 10, 10, $pending, 0, false, true, true);
        $statusService = $this->makeEmpty(IndexStatusServiceInterface::class, [
            'getStatusByIndexName' => function () use ($status, $statusException): IndexStatus {
                $this->statusReads++;
                if ($statusException !== null) {
                    throw $statusException;
                }

                return $status;
            },
        ]);
        $dispatcher = $this->makeEmpty(EventDispatcherInterface::class, [
            'dispatch' => function (object $event): object {
                $this->events[] = $event;

                return $event;
            },
        ]);
        $logger = $this->makeEmpty(LoggerInterface::class, [
            'warning' => function (string $message): void {
                $this->warnings[] = $message;
            },
            'error' => function (string $message): void {
                $this->errors[] = $message;
            },
        ]);

        $notifier = new IndexCompletionNotifier(
            $resolver,
            $rebuildState,
            $this->makeEmpty(IndexCountRepositoryInterface::class, ['countQueueEntries' => $pending]),
            $statusService,
            $dispatcher
        );
        $notifier->setLogger($logger);

        return $notifier;
    }
}
