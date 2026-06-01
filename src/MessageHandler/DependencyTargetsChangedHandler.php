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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Service\ElementServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use Pimcore\Messenger\DependencyTargetsChangedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final readonly class DependencyTargetsChangedHandler
{
    public function __construct(
        private IndexQueueServiceInterface $indexQueueService,
        private QueueMessagesDispatcher $queueMessagesDispatcher,
        private ElementServiceInterface $elementService,
        private SynchronousProcessingServiceInterface $synchronousProcessing,
    ) {
    }

    public function __invoke(DependencyTargetsChangedMessage $message): void
    {
        $assetIds = $this->collectAffectedAssetIds($message);

        foreach ($assetIds as $assetId) {
            $asset = $this->elementService->getElementByType($assetId, 'asset');
            if ($asset === null) {
                continue;
            }

            $this->indexQueueService
                ->updateIndexQueue(
                    $asset,
                    IndexQueueOperation::UPDATE->value,
                    processSynchronously: $this->synchronousProcessing->isEnabled(),
                    enqueueRelatedItems: false,
                )
                ->commit();
        }

        if (!empty($assetIds)) {
            $this->queueMessagesDispatcher->dispatchQueueMessages(
                $this->synchronousProcessing->isEnabled()
            );
        }
    }

    /**
     * @return int[]
     */
    private function collectAffectedAssetIds(DependencyTargetsChangedMessage $message): array
    {
        $assetIds = [];

        foreach ($message->getAddedTargets() as $target) {
            if ($target->getType() === 'asset') {
                $assetIds[$target->getId()] = true;
            }
        }

        foreach ($message->getRemovedTargets() as $target) {
            if ($target->getType() === 'asset') {
                $assetIds[$target->getId()] = true;
            }
        }

        return array_keys($assetIds);
    }
}
