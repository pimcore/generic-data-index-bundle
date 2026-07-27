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
use Pimcore\Bundle\GenericDataIndexBundle\Enum\Messenger\TransportName;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\ElementType;
use Pimcore\Bundle\GenericDataIndexBundle\EventSubscriber\DocumentIndexUpdateSubscriber;
use Pimcore\Bundle\GenericDataIndexBundle\Message\UpdateSiblingsMessage;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcherInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingRelatedIdsServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\SynchronousProcessingServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueueServiceInterface;
use Pimcore\Bundle\StaticResolverBundle\Lib\Cache\RuntimeCacheResolverInterface;
use Pimcore\Event\Model\DocumentEvent;
use Pimcore\Extension\Bundle\Installer\InstallerInterface;
use Pimcore\Model\Document;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

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
            'dispatch' => Expected::once(function (UpdateSiblingsMessage $message, array $stamps) {
                $this->assertSame(42, $message->getElementId());
                $this->assertSame(ElementType::DOCUMENT, $message->getElementType());
                $this->assertFalse($message->getResetChildrenIndexBy());
                $this->assertSame([], $stamps);

                return new Envelope($message);
            }),
        ]);

        $subscriber = new DocumentIndexUpdateSubscriber(
            $this->makeIndexQueueService(),
            $this->makeEmpty(InstallerInterface::class, ['isInstalled' => true]),
            $this->makeEmpty(QueueMessagesDispatcherInterface::class),
            $this->makeEmpty(RuntimeCacheResolverInterface::class, ['isRegistered' => false]),
            $this->makeEmpty(SynchronousProcessingServiceInterface::class, ['isEnabled' => false]),
            $this->makeEmpty(SynchronousProcessingRelatedIdsServiceInterface::class),
            $messageBus
        );

        $subscriber->updateDocument($event);
    }

    public function testUpdateDocumentDispatchesSynchronouslyWhenSyncProcessingEnabled(): void
    {
        $document = $this->makeEmpty(Document::class, [
            'getId' => 42,
        ]);

        $event = $this->makeEmpty(DocumentEvent::class, [
            'getDocument' => $document,
        ]);

        $messageBus = $this->makeEmpty(MessageBusInterface::class, [
            'dispatch' => Expected::once(function (UpdateSiblingsMessage $message, array $stamps) {
                $this->assertCount(1, $stamps);
                $this->assertInstanceOf(TransportNamesStamp::class, $stamps[0]);
                $this->assertSame([TransportName::SYNC->value], $stamps[0]->getTransportNames());

                return new Envelope($message);
            }),
        ]);

        $subscriber = new DocumentIndexUpdateSubscriber(
            $this->makeIndexQueueService(),
            $this->makeEmpty(InstallerInterface::class, ['isInstalled' => true]),
            $this->makeEmpty(QueueMessagesDispatcherInterface::class),
            $this->makeEmpty(RuntimeCacheResolverInterface::class, ['isRegistered' => false]),
            $this->makeEmpty(SynchronousProcessingServiceInterface::class, ['isEnabled' => true]),
            $this->makeEmpty(SynchronousProcessingRelatedIdsServiceInterface::class),
            $messageBus
        );

        $subscriber->updateDocument($event);
    }

    public function testUpdateDocumentDoesNothingWhenNotInstalled(): void
    {
        $event = $this->makeEmpty(DocumentEvent::class, [
            'getDocument' => Expected::never(),
        ]);

        $subscriber = new DocumentIndexUpdateSubscriber(
            $this->makeEmpty(IndexQueueServiceInterface::class, [
                'updateIndexQueue' => Expected::never(),
            ]),
            $this->makeEmpty(InstallerInterface::class, ['isInstalled' => false]),
            $this->makeEmpty(QueueMessagesDispatcherInterface::class),
            $this->makeEmpty(RuntimeCacheResolverInterface::class),
            $this->makeEmpty(SynchronousProcessingServiceInterface::class),
            $this->makeEmpty(SynchronousProcessingRelatedIdsServiceInterface::class),
            $this->makeEmpty(MessageBusInterface::class, [
                'dispatch' => Expected::never(),
            ])
        );

        $subscriber->updateDocument($event);
    }

    /**
     * IndexQueueServiceInterface is fluent, so the double has to return itself from both methods.
     */
    private function makeIndexQueueService(): IndexQueueServiceInterface
    {
        $indexQueueService = null;
        $returnSelf = static function () use (&$indexQueueService) {
            return $indexQueueService;
        };

        return $indexQueueService = $this->makeEmpty(IndexQueueServiceInterface::class, [
            'updateIndexQueue' => Expected::atLeastOnce($returnSelf),
            'commit' => $returnSelf,
        ]);
    }
}
