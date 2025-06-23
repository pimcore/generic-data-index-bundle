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
                try {
                    $this->messageBus->dispatch(new IndexUpdateQueueMessage($entries));
                } catch (Exception $exception) {
                    $this->logger->error(
                        'Dispatching IndexUpdateQueueMessage failed! ' .
                        get_class($exception) . ': ' . $exception->getMessage()
                    );

                    $dispatchId = $entries[0]['dispatched'] ?? null;
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
