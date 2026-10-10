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
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AdapterServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Workflow\MarkingStore\StateTableMarkingStore;
use stdClass;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @internal
 */
final class WorkflowPlaceIndexUpdateSubscriberTest extends Unit
{
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
    private function createSubscriber(): WorkflowPlaceIndexUpdateSubscriber
    {
        $connection = $this->makeEmpty(Connection::class, [
            'executeQuery' => Expected::never(),
            'executeStatement' => Expected::never(),
        ]);
        $indexQueueRepository = new IndexQueueRepository(
            $this->makeEmpty(EntityManagerInterface::class),
            $this->makeEmpty(TimeServiceInterface::class),
            $connection,
            $this->makeEmpty(DenormalizerInterface::class),
        );

        return new WorkflowPlaceIndexUpdateSubscriber(
            new Installer($connection, $this->makeEmpty(BundleInterface::class)),
            $this->makeEmpty(AdapterServiceInterface::class, ['getTypeAdapter' => Expected::never()]),
            $indexQueueRepository,
            $this->makeEmpty(TimeServiceInterface::class, ['getCurrentMillisecondTimestamp' => Expected::never()]),
            new QueueMessagesDispatcher(
                $this->makeEmpty(MessageBusInterface::class, ['dispatch' => Expected::never()]),
                $indexQueueRepository
            ),
        );
    }
}
