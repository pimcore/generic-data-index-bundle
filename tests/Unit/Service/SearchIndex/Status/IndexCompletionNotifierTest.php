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

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Entity\IndexQueue;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\DeploymentReindexScope;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexStatusState;
use Pimcore\Bundle\GenericDataIndexBundle\Event\IndexStatus\IndexCompleteEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\IndexStatusException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
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
    public function testTheEventFiresOnceTheQueuedRebuildIsProcessed(): void
    {
        $events = [];
        $cleared = [];
        $notifier = $this->createNotifier(
            queuedAt: 1,
            pending: 0,
            state: IndexStatusState::COMPLETE,
            events: $events,
            cleared: $cleared
        );

        $notifier->afterBatch([$this->entry('asset'), $this->entry('asset')]);

        $this->assertCount(1, $events, 'One event per index, not per entry');
        $this->assertSame('pimcore_asset', $events[0]->getIndexName());
        $this->assertSame(['asset'], $cleared);
    }

    public function testNothingHappensWithoutAQueuedRebuild(): void
    {
        $events = [];
        $cleared = [];
        $notifier = $this->createNotifier(queuedAt: null, pending: 0, state: IndexStatusState::COMPLETE, events: $events, cleared: $cleared);

        $notifier->afterBatch([$this->entry('asset')]);

        $this->assertSame([], $events);
        $this->assertSame([], $cleared);
    }

    public function testNothingHappensWhileEntriesAreStillPending(): void
    {
        $events = [];
        $cleared = [];
        $notifier = $this->createNotifier(queuedAt: 1, pending: 5, state: IndexStatusState::REBUILDING, events: $events, cleared: $cleared);

        $notifier->afterBatch([$this->entry('asset')]);

        $this->assertSame([], $events);
        $this->assertSame([], $cleared);
    }

    public function testAProcessedRebuildThatLeftTheIndexIncompleteClearsTheMarkerWithoutAnEvent(): void
    {
        $events = [];
        $cleared = [];
        $warnings = [];
        $notifier = $this->createNotifier(
            queuedAt: 1,
            pending: 0,
            state: IndexStatusState::INCOMPLETE,
            events: $events,
            cleared: $cleared,
            warnings: $warnings
        );

        $notifier->afterBatch([$this->entry('asset')]);

        $this->assertSame([], $events);
        $this->assertSame(['asset'], $cleared, 'The marker must not keep reporting a rebuild that is over');
        $this->assertCount(1, $warnings);
    }

    public function testAFailingCheckIsLoggedAndDoesNotFailTheBatch(): void
    {
        $events = [];
        $cleared = [];
        $errors = [];
        $notifier = $this->createNotifier(
            queuedAt: 1,
            pending: 0,
            state: IndexStatusState::COMPLETE,
            events: $events,
            cleared: $cleared,
            errors: $errors,
            statusException: new IndexStatusException('engine down')
        );

        $notifier->afterBatch([$this->entry('asset')]);

        $this->assertSame([], $events);
        $this->assertCount(1, $errors);
    }

    public function testEntriesOfUnknownIndicesAreSkipped(): void
    {
        $events = [];
        $cleared = [];
        $notifier = $this->createNotifier(queuedAt: 1, pending: 0, state: IndexStatusState::COMPLETE, events: $events, cleared: $cleared);

        $notifier->afterBatch([$this->entry('DeletedClass')]);

        $this->assertSame([], $events);
    }

    private function entry(string $indexName): IndexQueue
    {
        return (new IndexQueue())->setElementId(1)->setElementType('asset')->setElementIndexName($indexName);
    }

    /**
     * @param IndexCompleteEvent[] $events
     * @param string[] $cleared
     * @param string[] $warnings
     * @param string[] $errors
     */
    private function createNotifier(
        ?int $queuedAt,
        int $pending,
        IndexStatusState $state,
        array &$events,
        array &$cleared,
        array &$warnings = [],
        array &$errors = [],
        ?IndexStatusException $statusException = null,
    ): IndexCompletionNotifier {
        $target = IndexStateTarget::forScope(DeploymentReindexScope::ASSETS);
        $resolver = $this->makeEmpty(IndexStateTargetResolverInterface::class, [
            'fromElementIndexName' => static fn (string $name): ?IndexStateTarget => $name === 'asset' ? $target : null,
            'aliasName' => 'pimcore_asset',
        ]);
        $rebuildState = $this->makeEmpty(IndexRebuildStateServiceInterface::class, [
            'getQueuedAt' => $queuedAt,
            'clear' => static function (IndexStateTarget $t) use (&$cleared): void {
                $cleared[] = $t->getKey();
            },
        ]);
        $status = new IndexStatus('pimcore_asset', 'assets', $state, 10, 10, $pending, 0, false, true, true);
        $statusService = $this->makeEmpty(IndexStatusServiceInterface::class, [
            'getStatusByIndexName' => static function () use ($status, $statusException): IndexStatus {
                if ($statusException !== null) {
                    throw $statusException;
                }

                return $status;
            },
        ]);
        $dispatcher = $this->makeEmpty(EventDispatcherInterface::class, [
            'dispatch' => static function (object $event) use (&$events): object {
                $events[] = $event;

                return $event;
            },
        ]);
        $logger = $this->makeEmpty(LoggerInterface::class, [
            'warning' => static function (string $message) use (&$warnings): void {
                $warnings[] = $message;
            },
            'error' => static function (string $message) use (&$errors): void {
                $errors[] = $message;
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
