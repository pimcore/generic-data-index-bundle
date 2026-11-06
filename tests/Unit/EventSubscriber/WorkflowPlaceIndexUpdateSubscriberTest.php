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
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Pimcore\Bundle\GenericDataIndexBundle\EventSubscriber\WorkflowPlaceIndexUpdateSubscriber;
use Pimcore\Bundle\GenericDataIndexBundle\Installer;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\HitData;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AbstractElementTypeAdapter;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AdapterServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use Pimcore\Event\AssetEvents;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\DocumentEvents;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Event\Workflow\GlobalActionEvent;
use Pimcore\Event\WorkflowEvents as PimcoreWorkflowEvents;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Workflow\ExpressionService;
use Pimcore\Workflow\GlobalAction;
use Pimcore\Workflow\MarkingStore\StateTableMarkingStore;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\WorkflowEvents;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @internal
 */
final class WorkflowPlaceIndexUpdateSubscriberTest extends Unit
{
    public function testSubscribedEvents(): void
    {
        // The place changes are written to the queue and dispatched when the request, command or message ends.
        $events = WorkflowPlaceIndexUpdateSubscriber::getSubscribedEvents();

        $this->assertSame('onEntered', $events[WorkflowEvents::ENTERED]);
        $this->assertSame('onPostGlobalAction', $events[PimcoreWorkflowEvents::POST_GLOBAL_ACTION]);
        // A delete never drops a collected hit: the queue write keeps a queued DELETE, and a delete which is rolled
        // back with its parent still needs the place update (R3-1).
        foreach ([AssetEvents::POST_DELETE, DataObjectEvents::POST_DELETE, DocumentEvents::POST_DELETE] as $event) {
            $this->assertArrayNotHasKey($event, $events);
        }
        foreach ([
            KernelEvents::TERMINATE,
            ConsoleEvents::TERMINATE,
            WorkerMessageHandledEvent::class,
            WorkerMessageFailedEvent::class,
        ] as $event) {
            $this->assertSame('dispatchQueueMessages', $events[$event]);
        }
    }

    public function testNothingIsWrittenWithoutPlaceChange(): void
    {
        $this->createSubscriber()->dispatchQueueMessages();
    }

    public function testCollectedHitStaysAfterTheElementWasDeleted(): void
    {
        // R3-1: the delete of a child can be rolled back with its parent. The hit must survive, the queue write
        // keeps a queued DELETE of an element which really is gone (keepQueuedDelete, IndexQueueTest).
        $subscriber = $this->createSubscriber();
        $this->collect($subscriber, '5', 'dataObject');

        // the subscriber is registered like in the kernel: the delete events must not touch the collected hit
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($subscriber);
        $dispatcher->dispatch(
            new DataObjectEvent($this->makeEmpty(Concrete::class, ['getId' => 5])),
            DataObjectEvents::POST_DELETE
        );

        $this->assertSame(['dataObject_5'], array_keys($this->getPending($subscriber)));
    }

    public function testFailingQueueWriteDoesNotFailTheRequestAndLogsTheElements(): void
    {
        // R2-3: the list is emptied before the write; when the write fails the hits are lost, which is logged.
        // The next save or reindex of the element repairs the index.
        $connection = $this->makeEmpty(Connection::class, [
            'quote' => static fn (mixed $value): string => "'" . $value . "'",
            'executeQuery' => static function (): never {
                throw new RuntimeException('queue table is gone');
            },
        ]);
        $logged = [];
        $logger = $this->makeEmpty(LoggerInterface::class, [
            'error' => function (string $message, array $context) use (&$logged): void {
                $logged = $context;
            },
        ]);
        $subscriber = $this->createSubscriber($connection, $logger);
        $this->collect($subscriber, '5', 'dataObject');

        $subscriber->dispatchQueueMessages();

        $this->assertSame(['5'], $logged['elementIds']);
        $this->assertSame([], $this->getPending($subscriber));
    }

    public function testWorkflowWithOtherMarkingStoreIsIgnored(): void
    {
        // The place is part of the element: the regular index update after its save covers it.
        $subscriber = $this->createSubscriber();

        $subscriber->onEntered($this->createEvent($this->makeEmpty(Concrete::class), new MethodMarkingStore()));
        $subscriber->dispatchQueueMessages();
    }

    public function testSubjectWhichIsNoElementIsIgnored(): void
    {
        $subscriber = $this->createSubscriber();

        $subscriber->onEntered($this->createEvent(new stdClass(), new StateTableMarkingStore('product_workflow')));
        $subscriber->dispatchQueueMessages();
    }

    public function testGlobalActionWithoutTargetPlacesIsIgnored(): void
    {
        $subscriber = $this->createSubscriber();
        $workflow = $this->makeEmpty(WorkflowInterface::class, [
            'getMarkingStore' => new StateTableMarkingStore('product_workflow'),
        ]);
        $globalAction = new GlobalAction(
            'notify',
            [],
            $this->makeEmpty(ExpressionService::class),
            'product_workflow'
        );

        $subscriber->onPostGlobalAction(new GlobalActionEvent($workflow, $this->makeEmpty(Concrete::class), $globalAction));
        $subscriber->dispatchQueueMessages();
    }

    private function collect(WorkflowPlaceIndexUpdateSubscriber $subscriber, string $id, string $type): void
    {
        // Collecting needs Installer::isInstalled() (booted kernel), so the pending list is filled directly.
        (new ReflectionProperty($subscriber, 'pendingHits'))->setValue($subscriber, [
            $type . '_' . $id => new HitData($id, $type, 'data_object_object'),
        ]);
    }

    /**
     * @return array<string, HitData>
     */
    private function getPending(WorkflowPlaceIndexUpdateSubscriber $subscriber): array
    {
        return (new ReflectionProperty($subscriber, 'pendingHits'))->getValue($subscriber);
    }

    private function createEvent(object $subject, MarkingStoreInterface $markingStore): EnteredEvent
    {
        return new EnteredEvent(
            $subject,
            new Marking(['draft' => 1]),
            null,
            $this->makeEmpty(WorkflowInterface::class, ['getMarkingStore' => $markingStore])
        );
    }

    /**
     * Fails when a guard lets the event through: Installer::isInstalled() needs a booted kernel (not available in
     * unit tests), and the Expected::never() expectations are verified after the test.
     */
    private function createSubscriber(
        ?Connection $connection = null,
        ?LoggerInterface $logger = null,
    ): WorkflowPlaceIndexUpdateSubscriber {
        $withWrites = $connection !== null;
        $connection ??= $this->makeEmpty(Connection::class, [
            'executeQuery' => Expected::never(),
            'executeStatement' => Expected::never(),
        ]);
        $indexQueueRepository = new IndexQueueRepository(
            $this->makeEmpty(EntityManagerInterface::class),
            $this->makeEmpty(TimeServiceInterface::class),
            $connection,
            $this->makeEmpty(DenormalizerInterface::class),
        );

        $subscriber = new WorkflowPlaceIndexUpdateSubscriber(
            new Installer($connection, $this->makeEmpty(BundleInterface::class)),
            $this->makeEmpty(AdapterServiceInterface::class, [
                'getTypeAdapter' => $this->makeEmpty(AbstractElementTypeAdapter::class, [
                    'getElementType' => 'dataObject',
                ]),
            ]),
            $indexQueueRepository,
            $this->makeEmpty(TimeServiceInterface::class, [
                'getCurrentMillisecondTimestamp' => $withWrites ? 1 : Expected::never(),
            ]),
            new QueueMessagesDispatcher(
                $this->makeEmpty(MessageBusInterface::class, ['dispatch' => Expected::never()]),
                $indexQueueRepository
            ),
        );
        if ($logger !== null) {
            $subscriber->setLogger($logger);
        }

        return $subscriber;
    }
}
