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

namespace Pimcore\Bundle\GenericDataIndexBundle\MessageHandler;

use Pimcore\Bundle\GenericDataIndexBundle\Message\DispatchQueueMessagesMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Message\IndexUpdateQueueMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class DispatchQueueMessagesHandler
{
    public function __construct(
        protected readonly IndexQueueService $indexQueueService,
        protected readonly MessageBusInterface $messageBus
    ) {
    }

    public function __invoke(DispatchQueueMessagesMessage $message): void
    {
        $batchSize = 400;
        while (true) {
            $entries = $this->indexQueueService->getUnhandledIndexQueueEntries(true, $batchSize);
            $amountOfEntries = count($entries);

            if ($amountOfEntries > 0) {
                $this->messageBus->dispatch(new IndexUpdateQueueMessage($entries));
            }

            if ($amountOfEntries < $batchSize) {
                break;
            }
        }
    }
}