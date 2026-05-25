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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\EventSubscriber;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\EventSubscriber\DocumentIndexUpdateSubscriber;
use Pimcore\Bundle\GenericDataIndexBundle\Installer;
use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateSiblingsMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingRelatedIdsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Event\Model\DocumentEvent;
use Pimcore\Model\Document;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
final class DocumentIndexUpdateSubscriberTest extends Unit
{
    public function testUpdateDocumentDispatchesUpdateSiblingsMessage(): void
    {
        $document = $this->makeEmpty(Document::class, [
            'getId' => 42,
        ]);

        $event = $this->makeEmpty(DocumentEvent::class, [
            'getDocument' => $document,
        ]);

        $messageBus = $this->makeEmpty(MessageBusInterface::class, [
            'dispatch' => Expected::once(function (UpdateSiblingsMessage $message) {
                $this->assertSame(42, $message->getElementId());
                $this->assertSame(ElementType::DOCUMENT->value, $message->getElementType());
                $this->assertFalse($message->getResetChildrenIndexBy());

                return new Envelope($message);
            }),
        ]);

        $subscriber = new DocumentIndexUpdateSubscriber(
            $this->makeEmpty(IndexQueueServiceInterface::class, [
                'updateIndexQueue' => Expected::atLeastOnce(
                    $this->makeEmpty(IndexQueueServiceInterface::class, ['commit' => null])
                ),
            ]),
            $this->makeEmpty(Installer::class, ['isInstalled' => true]),
            $this->makeEmpty(QueueMessagesDispatcher::class),
            $this->makeEmpty(RuntimeCacheResolverInterface::class, ['isRegistered' => false]),
            $this->makeEmpty(SynchronousProcessingServiceInterface::class),
            $this->makeEmpty(SynchronousProcessingRelatedIdsServiceInterface::class),
            $messageBus
        );

        $subscriber->updateDocument($event);
    }
}
