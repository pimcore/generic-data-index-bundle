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

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Message\EnqueueRelatedIdsMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\ElementServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use Pimcore\Model\DataObject\AbstractObject;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final readonly class EnqueueRelatedIdsHandler
{
    public function __construct(
        private IndexQueueServiceInterface $indexQueueService,
        private QueueMessagesDispatcher $queueMessagesDispatcher,
        private ElementServiceInterface $elementService
    ) {
    }

    public function __invoke(EnqueueRelatedIdsMessage $message): void
    {
        $inheritanceBackup = null;
        $elementType = $message->getElementType();
        $element = $this->elementService->getElementByType($message->getElementId(), $elementType->value);

        if ($element === null) {
            return;
        }

        if ($elementType === ElementType::DATA_OBJECT) {
            $inheritanceBackup = AbstractObject::getGetInheritedValues();
            AbstractObject::setGetInheritedValues(true);
        }

        $this->indexQueueService->updateIndexQueue(
            $element,
            $message->getOperation(),
            !$message->getAddParentElement(),
            true,
            false
        )->commit();

        if ($inheritanceBackup !== null) {
            AbstractObject::setGetInheritedValues($inheritanceBackup);
        }

        $this->queueMessagesDispatcher->dispatchQueueMessages();
    }
}
