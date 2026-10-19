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
            try {
                $this->notifyIfComplete((string) $indexName);
            } catch (Exception $e) {
                // The batch is processed and removed already; a failed notification must not fail it.
                $this->logger->error('Checking the index status after a batch failed', [
                    'indexName' => $indexName,
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * @throws Exception
     */
    private function notifyIfComplete(string $elementIndexName): void
    {
        $target = $this->targetResolver->fromElementIndexName($elementIndexName);
        if ($target === null || $this->rebuildStateService->getQueuedAt($target) === null) {
            return;
        }
        if ($this->countRepository->countQueueEntries($elementIndexName) > 0) {
            return;
        }

        // The queued rebuild is processed, whatever the index looks like now; the marker must not keep
        // reporting a rebuild that is over.
        $this->rebuildStateService->clear($target);
        $status = $this->indexStatusService->getStatusByIndexName($this->targetResolver->aliasName($target));
        if (!$status->isComplete()) {
            $this->logger->warning('Rebuild processed, but the index is not complete', [
                'indexName' => $status->getIndexName(),
                'state' => $status->getState()->value,
            ]);

            return;
        }

        $this->logger->info('Index complete after rebuild', ['indexName' => $status->getIndexName()]);
        $this->eventDispatcher->dispatch(new IndexCompleteEvent($status));
    }
}
