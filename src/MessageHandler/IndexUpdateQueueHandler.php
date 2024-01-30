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
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @internal
 */
#[AsMessageHandler]
final class IndexUpdateQueueHandler
{
    public function __construct(
        private readonly IndexQueueServiceInterface $indexQueueService,
        private readonly IndexQueueRepository $indexQueueRepository,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(IndexUpdateQueueMessage $message): void
    {
        $entries = [];
        foreach ($message->getEntries() as $entry) {
            $entries[] = $this->indexQueueRepository->denormalizeDatabaseEntry($entry);
        }

        $this->indexQueueService->handleIndexQueueEntries($entries);
    }
}
