<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue;

use Doctrine\DBAL\Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Message\IndexUpdateQueueMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
final class QueueMessageService implements QueueMessageServiceInterface
{
    public function __construct(
        private readonly IndexQueueRepository $indexQueueRepository,
        private readonly MessageBusInterface $messageBus
    ) {
    }

    public function handleMessage(
        int $entriesCount,
        int $maxBatchSize
    ): void {
        while(true) {
            $entries = $this->indexQueueRepository->getUnhandledIndexQueueEntries(true, $maxBatchSize);
            $amountOfEntries = count($entries);
            if ($amountOfEntries > 0) {
                $this->messageBus->dispatch(new IndexUpdateQueueMessage($entries));
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
