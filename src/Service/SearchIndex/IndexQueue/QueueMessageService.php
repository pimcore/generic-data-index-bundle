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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Message\IndexUpdateQueueMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
final class QueueMessageService implements QueueMessageServiceInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly IndexQueueRepository $indexQueueRepository,
        private readonly MessageBusInterface $messageBus
    ) {
    }

    public function handleMessage(
        int $entriesCount,
        int $maxBatchSize
    ): void {
        while (true) {
            $entries = $this->indexQueueRepository->getUnhandledIndexQueueEntries(
                true,
                $maxBatchSize
            );
            $amountOfEntries = count($entries);
            if ($amountOfEntries > 0) {
                // The dispatch id was just stamped on this claimed batch by getUnhandledIndexQueueEntries().
                // Logging it here - on the dispatch side - lets a batch be correlated with the matching
                // "Processing index queue batch" log even when the message is never handled (worker died,
                // transport dropped it), which the processing-side log alone cannot show.
                $dispatchId = $entries[0]['dispatched'] ?? null;

                try {
                    $this->logger->info('Dispatching index queue batch', [
                        'dispatchId' => $dispatchId,
                        'entries' => $amountOfEntries,
                    ]);
                    $this->messageBus->dispatch(new IndexUpdateQueueMessage($entries));
                } catch (Exception $exception) {
                    $this->logger->error('Dispatching IndexUpdateQueueMessage failed', [
                        'dispatchId' => $dispatchId,
                        'entries' => $amountOfEntries,
                        'exception' => $exception,
                    ]);

                    if ($dispatchId !== null) {
                        $this->indexQueueRepository->resetDispatchedItems($dispatchId);
                    }

                    break;
                }
            }
            if ($amountOfEntries < $maxBatchSize) {
                break;
            }
        }
    }

    public function getMaxBatchSize(
        int $entriesCount,
        int $workerCount,
        int $minBatchSize,
        int $maxBatchSize
    ): int {
        if ($workerCount === 1) {
            return $maxBatchSize;
        }

        $itemsPerWorker = (int)floor($entriesCount / $workerCount);

        return match (true) {
            $itemsPerWorker < $minBatchSize => $minBatchSize,
            $itemsPerWorker < $maxBatchSize => $itemsPerWorker,
            default => $maxBatchSize,
        };
    }
}
