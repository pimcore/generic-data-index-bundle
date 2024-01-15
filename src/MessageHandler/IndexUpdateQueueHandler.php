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

use Pimcore\Bundle\GenericDataIndexBundle\Message\IndexUpdateQueueMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class IndexUpdateQueueHandler
{
    public function __construct(
        protected readonly IndexQueueService $indexQueueService,
        protected readonly MessageBusInterface $messageBus
    )
    {
    }

    public function __invoke(IndexUpdateQueueMessage $message)
    {
        $entries = [];
        foreach ($message->getEntries() as $entry) {
            $entries[] = $this->indexQueueService->denormalizeDatabaseEntry($entry);
        }

        $this->indexQueueService->handleIndexQueueEntries($entries);
    }
}
