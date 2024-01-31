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

    /**
     * @throws Exception
     */
    public function handleMessage(
        int $entriesCount,
        int $maxBatchSize,
        array $entries
    ): void
    {
        if ($entriesCount > $maxBatchSize) {
            $chunks = array_chunk($entries, $maxBatchSize);
            foreach($chunks as $chunk) {
                $this->dispatchMessage($chunk, $maxBatchSize);
            }
        } else {
            $this->dispatchMessage($entries, $maxBatchSize);
        }
    }

    public function getMaxBatchSize(
        int $entriesCount,
        int $workerCount,
        int $minBatchSize,
        int $maxBatchSize
    ): int
    {
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

    /**
     * @throws Exception
     */
    private function dispatchMessage(array $items, int $limit): void
    {
        $this->messageBus->dispatch(new IndexUpdateQueueMessage($items));
        $this->indexQueueRepository->dispatchItems($limit);
    }
}