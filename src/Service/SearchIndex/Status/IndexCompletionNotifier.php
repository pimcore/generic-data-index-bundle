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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Status;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Event\IndexStatus\IndexCompleteEvent;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Deployment\IndexStateTarget;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexCountRepositoryInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class IndexCompletionNotifier implements IndexCompletionNotifierInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly IndexStateTargetResolverInterface $targetResolver,
        private readonly IndexRebuildStateServiceInterface $rebuildStateService,
        private readonly IndexCountRepositoryInterface $countRepository,
        private readonly IndexStatusServiceInterface $indexStatusService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function afterBatch(array $entries): void
    {
        $indexNames = [];
        foreach ($entries as $entry) {
            $indexNames[$entry->getElementIndexName()] = true;
        }

        foreach (array_keys($indexNames) as $indexName) {
            $target = $this->targetResolver->fromElementIndexName((string) $indexName);
            if ($target !== null) {
                $this->check($target);
            }
        }
    }

    public function afterEnqueue(IndexStateTarget $target): void
    {
        $this->check($target);
    }

    private function check(IndexStateTarget $target): void
    {
        try {
            $this->notifyIfComplete($target);
        } catch (Exception $e) {
            // The queue entries are processed already; a failed check must not fail the batch or the enqueue.
            // The marker stays, so the next batch of this index checks again.
            $this->logger->error('Checking the index status after a batch failed', [
                'indexName' => $this->targetResolver->aliasName($target),
                'exception' => $e,
            ]);
        }
    }

    /**
     * @throws Exception
     */
    private function notifyIfComplete(IndexStateTarget $target): void
    {
        // No rebuild queued, its elements still being queued (an empty queue says nothing yet), or entries waiting.
        $marker = $this->rebuildStateService->getMarker($target);
        if ($marker === null
            || !$marker->isEnqueued()
            || $this->countRepository->countQueueEntries($this->targetResolver->elementIndexName($target)) > 0
        ) {
            return;
        }

        // Read the status while the marker is still there: if the engine fails here, the next batch retries.
        $status = $this->indexStatusService->getStatusByIndexName($this->targetResolver->aliasName($target));

        // Only the caller that removes the marker reports; another worker may have finished the same rebuild
        // between the two reads above, or a newer rebuild has replaced the marker and is reported on its own.
        if (!$this->rebuildStateService->claim($target, $marker)) {
            return;
        }

        if (!$status->isComplete()) {
            $this->logger->warning('Rebuild processed, but the index is not complete', [
                'indexName' => $status->getIndexName(),
                'state' => $status->getState()->value,
            ]);
        } else {
            $this->logger->info('Index complete after rebuild', ['indexName' => $status->getIndexName()]);
            $this->eventDispatcher->dispatch(new IndexCompleteEvent($status));
        }
    }
}
