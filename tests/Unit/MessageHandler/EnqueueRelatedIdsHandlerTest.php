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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\MessageHandler;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Message\EnqueueRelatedIdsMessage;
use Pimcore\Bundle\GenericDataIndexBundle\MessageHandler\EnqueueRelatedIdsHandler;
use Pimcore\Bundle\GenericDataIndexBundle\Service\ElementServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use ReflectionClass;

/**
 * @internal
 */
final class EnqueueRelatedIdsHandlerTest extends Unit
{
    public function testDeletedElementSkipsProcessing(): void
    {
        // QueueMessagesDispatcher is final and cannot be doubled; the guard
        // returns before it is used, so an uninitialized instance is safe here.
        $queueMessagesDispatcher = (new ReflectionClass(QueueMessagesDispatcher::class))
            ->newInstanceWithoutConstructor();

        $handler = new EnqueueRelatedIdsHandler(
            $this->makeEmpty(IndexQueueServiceInterface::class, [
                'updateIndexQueue' => Expected::never(),
            ]),
            $queueMessagesDispatcher,
            $this->makeEmpty(ElementServiceInterface::class, [
                'getElementByType' => null,
            ])
        );

        $handler(new EnqueueRelatedIdsMessage(
            999,
            ElementType::ASSET,
            IndexQueueOperation::UPDATE->value,
            false
        ));
    }
}
