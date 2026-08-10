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
final readonly class IndexUpdateQueueHandler
{
    public function __construct(
        private IndexQueueServiceInterface $indexQueueService,
        private IndexQueueRepository $indexQueueRepository,
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
