<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\MessageHandler;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Message\DispatchQueueMessagesMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessageServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final class DispatchQueueMessagesHandler
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly IndexQueueRepository $indexQueueRepository,
        private readonly QueueMessagesDispatcher $queueMessagesDispatcher,
        private readonly QueueMessageServiceInterface $queueMessageService,
        private readonly array $queueSettings,
    ) {
    }

    public function __invoke(DispatchQueueMessagesMessage $message): void
    {
        try {
            $entriesCount = $this->indexQueueRepository->countIndexQueueEntries();
            if ($entriesCount === 0) {
                return;
            }

            $realMaxBatchSize = $this->queueMessageService->getMaxBatchSize(
                $entriesCount,
                $this->queueSettings['worker_count'],
                $this->queueSettings['min_batch_size'],
                $this->queueSettings['max_batch_size']
            );

            $this->queueMessageService->handleMessage(
                $entriesCount,
                $realMaxBatchSize
            );
        } catch (Exception $e) {
            $this->logger->warning('Dispatching Queue Message failed: ' . $e);
        } finally {
            $this->queueMessagesDispatcher->clearPendingState();
        }
    }
}
